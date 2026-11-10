<?php

namespace Shef\FixMoizvonki;

use Bitrix\Crm\Integration\StorageType;
use Bitrix\Main\Error;
use Bitrix\Main\IO;
use Bitrix\Main\Loader;
use Bitrix\Main\Result;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Voximplant\StatisticTable;

/**
 * Скачивает запись «Мои звонки» и прикрепляет её к звонку и делу так же, как ядро
 * делает для обычной телефонии (Bitrix\Voximplant\Rest\Helper::attachFile):
 * файл в Диск, CALL_RECORD_ID/CALL_WEBDAV_ID в статистике, STORAGE_ELEMENT_IDS в деле.
 *
 * Штатный Helper::attachRecordWithUrl не подходит: он пропускает только mp3, flac, wav,
 * а «Мои звонки» отдают m4a (audio/mp4).
 */
final class RecordAttacher
{
    /** Временная ошибка: стоит повторить позже. */
    public const ERR_RETRY = 'RETRY';
    /** Повтор не поможет. */
    public const ERR_FATAL = 'FATAL';

    private const MAX_REDIRECTS = 3;

    /**
     * @throws \Bitrix\Main\LoaderException если не установлен crm, voximplant или disk
     */
    public static function attach(int $activityId): Result
    {
        Loader::requireModule('crm');
        Loader::requireModule('voximplant');
        Loader::requireModule('disk');

        $activity = \CCrmActivity::GetByID($activityId, false);
        if (!is_array($activity)) {
            return self::fail(self::ERR_FATAL, 'дело не найдено');
        }
        if (($activity['PROVIDER_ID'] ?? '') !== 'VOXIMPLANT_CALL') {
            return self::ok('пропуск: дело не звонок телефонии');
        }

        $description = (string)($activity['DESCRIPTION'] ?? '');
        $url = LinkParser::extractRecordUrl($description);
        if ($url === null) {
            return self::ok('пропуск: ссылки на запись в описании нет');
        }

        $statistic = self::findStatistic($activity);
        if ($statistic === null) {
            return self::fail(self::ERR_RETRY, 'статистика звонка ещё не создана');
        }
        if (!empty($statistic['CALL_RECORD_ID'])) {
            return self::ok('пропуск: у звонка уже есть запись');
        }

        $name = LinkParser::fileNameFromUrl($url, 'moizvonki-' . $activityId . '.m4a');
        $tempPath = null;
        try {
            $download = self::download($url, $name);
            if (!$download->isSuccess()) {
                return $download;
            }
            ['path' => $tempPath, 'type' => $type] = $download->getData();

            return self::store($activity, $statistic, $url, $name, $tempPath, $type);
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /** Статистика звонка: сначала по CALL_ID из ORIGIN_ID дела, затем по ID дела. */
    private static function findStatistic(array $activity): ?array
    {
        $callId = LinkParser::callIdFromOriginId((string)($activity['ORIGIN_ID'] ?? ''));
        if ($callId !== null) {
            $row = StatisticTable::getByCallId($callId);
            if (is_array($row)) {
                return $row;
            }
        }

        $row = StatisticTable::getRow(['filter' => ['=CRM_ACTIVITY_ID' => (int)$activity['ID']]]);

        return is_array($row) ? $row : null;
    }

    /**
     * Скачивает файл во временный каталог. Редиректы идут вручную, каждый адрес проверяем
     * по белому списку. Частные IP закрыты (SSRF): ссылку присылает внешний сервис.
     */
    private static function download(string $url, string $name): Result
    {
        $tempPath = \CFile::GetTempName('', $name);
        IO\Directory::createDirectory(IO\Path::getDirectory($tempPath));

        for ($i = 0; $i <= self::MAX_REDIRECTS; $i++) {
            $handle = fopen($tempPath, 'wb');
            if ($handle === false) {
                return self::fail(self::ERR_RETRY, 'не удалось создать временный файл');
            }

            $http = new HttpClient([
                'redirect' => false,
                'privateIp' => false,
                'socketTimeout' => 10,
                'streamTimeout' => 60,
                'bodyLengthMax' => Config::MAX_BYTES,
            ]);
            $http->setOutputStream($handle);
            $ok = $http->query(HttpClient::HTTP_GET, $url);
            fclose($handle);

            if ($ok === false) {
                @unlink($tempPath);

                return self::fail(self::ERR_RETRY, 'ошибка сети: ' . implode('; ', $http->getError()));
            }

            $status = (int)$http->getStatus();
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $next = self::resolveLocation($url, (string)$http->getHeaders()->get('Location'));
                if ($next === null || !LinkParser::isAllowedUrl($next)) {
                    @unlink($tempPath);

                    return self::fail(self::ERR_FATAL, 'редирект на недопустимый адрес');
                }
                $url = $next;
                continue;
            }

            if ($status !== 200) {
                @unlink($tempPath);
                // 4xx, кроме 408 и 429: запись не появится; 5xx и прочее: сервис мог ответить временно
                $fatal = $status >= 400 && $status < 500 && !in_array($status, [408, 429], true);

                return self::fail($fatal ? self::ERR_FATAL : self::ERR_RETRY, "сервер записи ответил HTTP {$status}");
            }

            $type = strtolower(trim(explode(';', (string)$http->getHeaders()->getContentType())[0]));
            if (!str_starts_with($type, 'audio/')) {
                @unlink($tempPath);

                return self::fail(self::ERR_FATAL, "это не аудио: {$type}");
            }
            if ((int)@filesize($tempPath) <= 0) {
                @unlink($tempPath);

                return self::fail(self::ERR_RETRY, 'скачан пустой файл');
            }

            $result = new Result();
            $result->setData(['path' => $tempPath, 'type' => $type]);

            return $result;
        }

        @unlink($tempPath);

        return self::fail(self::ERR_FATAL, 'слишком много редиректов');
    }

    private static function resolveLocation(string $base, string $location): ?string
    {
        if ($location === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || $location[0] !== '/') {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'] . $location;
    }

    /** Кладёт файл в Диск (как ядро), затем прикрепляет к делу и к статистике звонка. */
    private static function store(
        array $activity,
        array $statistic,
        string $url,
        string $name,
        string $tempPath,
        string $type
    ): Result {
        $userId = (int)$statistic['PORTAL_USER_ID'];
        $startDate = $statistic['CALL_START_DATE'] ?? null;
        $folderName = is_object($startDate) ? $startDate->format('Y-m') : date('Y-m');

        $folder = \CVoxImplantDiskHelper::GetRecordsFolder($folderName);
        if (!$folder) {
            return self::fail(self::ERR_RETRY, 'не удалось получить папку записей в Диске');
        }

        $rights = \Bitrix\Disk\Driver::getInstance()->getRightsManager();
        $accessCodes = [[
            'ACCESS_CODE' => 'U' . $userId,
            'TASK_ID' => $rights->getTaskIdByName($rights::TASK_FULL),
        ]];

        $fileArray = \CFile::MakeFileArray($tempPath);
        if (!is_array($fileArray)) {
            return self::fail(self::ERR_RETRY, 'не удалось прочитать скачанный файл');
        }
        $fileArray['name'] = $name;
        $fileArray['type'] = $type;

        $file = $folder->uploadFile($fileArray, ['NAME' => $name, 'CREATED_BY' => $userId], $accessCodes);
        if (!$file) {
            $errors = $folder->getErrors();

            return self::fail(
                self::ERR_RETRY,
                'Диск не принял файл: ' . ($errors ? $errors[0]->getMessage() : 'без пояснения')
            );
        }

        $updated = \CCrmActivity::Update(
            (int)$activity['ID'],
            [
                'STORAGE_TYPE_ID' => StorageType::Disk,
                'STORAGE_ELEMENT_IDS' => [$file->getId()],
                'DESCRIPTION' => LinkParser::stripRecordBlock((string)$activity['DESCRIPTION']),
            ],
            false
        );
        if (!$updated) {
            $file->markDeleted($userId); // не оставляем в Диске файл, который никуда не прикреплён

            return self::fail(self::ERR_RETRY, 'не удалось обновить дело: ' . \CCrmActivity::GetLastErrorMessage());
        }

        StatisticTable::update($statistic['ID'], [
            'CALL_RECORD_ID' => $file->getFileId(),
            'CALL_WEBDAV_ID' => $file->getId(),
            'CALL_RECORD_URL' => $url,
        ]);

        return self::ok('прикреплено', ['fileId' => (int)$file->getId(), 'bytes' => (int)@filesize($tempPath)]);
    }

    /** Успех с пояснением: Agent пишет его в лог, чтобы по журналу было видно, что произошло. */
    private static function ok(string $status, array $data = []): Result
    {
        $result = new Result();
        $result->setData(['status' => $status] + $data);

        return $result;
    }

    private static function fail(string $code, string $message): Result
    {
        $result = new Result();
        $result->addError(new Error($message, $code));

        return $result;
    }
}
