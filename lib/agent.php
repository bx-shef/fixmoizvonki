<?php

namespace Shef\FixMoizvonki;

use Bitrix\Main\Application;

/**
 * Обработка дела: сразу после ответа (фоновая задача) и запасной вариант на случай неудачи
 * (разовый агент с повторами: статистика звонка может появиться чуть позже дела, а скачивание
 * не всегда удаётся с первой попытки).
 */
final class Agent
{
    private const DONE = 'done';
    private const RETRY = 'retry';

    /** Постановка повтора агентом. Задержка в секундах до первого запуска. */
    public static function schedule(int $activityId, int $attempt = 1, int $delay = 10): void
    {
        if ($activityId <= 0) {
            return;
        }

        \CAgent::AddAgent(
            self::command($activityId, $attempt),
            Config::MODULE_ID,
            'N',
            Config::RETRY_INTERVAL,
            '',
            'Y',
            date('d.m.Y H:i:s', time() + $delay)
        );
    }

    /** Фоновая задача из Handler: выполняется сразу после ответа. Не вышло: повтор агентом. */
    public static function runNow($activityId = 0): void
    {
        $activityId = (int)$activityId;
        if (self::process($activityId, 1) === self::RETRY) {
            self::schedule($activityId, 2, Config::RETRY_INTERVAL);
        }
    }

    /** Вызывается планировщиком агентов. Пустая строка: больше не запускать. */
    public static function run($activityId = 0, $attempt = 1): string
    {
        $activityId = (int)$activityId;
        $attempt = (int)$attempt;

        if (self::process($activityId, $attempt) === self::RETRY && $attempt < Config::MAX_ATTEMPTS) {
            return self::command($activityId, $attempt + 1);
        }

        return '';
    }

    private static function process(int $activityId, int $attempt): string
    {
        // Одно дело обрабатывает один процесс: события Add и Update могут прийти оба.
        $connection = Application::getConnection();
        $lockName = 'shef.fixmoizvonki.' . $activityId;
        if (!$connection->lock($lockName, 0)) {
            return self::DONE;
        }

        try {
            $result = RecordAttacher::attach($activityId);
        } catch (\Throwable $e) {
            Logging::write('error', 'дело {activityId}, попытка {attempt}: исключение {error}', [
                'activityId' => $activityId,
                'attempt' => $attempt,
                'error' => get_class($e) . ': ' . $e->getMessage(),
            ]);

            return self::RETRY;
        } finally {
            $connection->unlock($lockName);
        }

        if ($result->isSuccess()) {
            $data = $result->getData();
            Logging::write('info', 'дело {activityId}, попытка {attempt}: {status}{details}', [
                'activityId' => $activityId,
                'attempt' => $attempt,
                'status' => (string)($data['status'] ?? 'готово'),
                'details' => isset($data['fileId'])
                    ? ' (файл ' . $data['fileId'] . ', ' . ($data['bytes'] ?? 0) . ' байт)'
                    : '',
            ]);

            return self::DONE;
        }

        $error = $result->getErrors()[0];
        Logging::write('warning', 'дело {activityId}, попытка {attempt}: [{code}] {reason}', [
            'activityId' => $activityId,
            'attempt' => $attempt,
            'code' => $error->getCode(),
            'reason' => $error->getMessage(),
        ]);

        return $error->getCode() === RecordAttacher::ERR_RETRY ? self::RETRY : self::DONE;
    }

    private static function command(int $activityId, int $attempt): string
    {
        return '\\Shef\\FixMoizvonki\\Agent::run(' . $activityId . ',' . $attempt . ');';
    }
}
