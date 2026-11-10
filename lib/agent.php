<?php

namespace Shef\FixMoizvonki;

/**
 * Разовый агент с повторами: статистика звонка может появиться чуть позже дела,
 * а скачивание не всегда удаётся с первой попытки.
 */
final class Agent
{
    public static function schedule(int $activityId, int $attempt = 1): void
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
            date('d.m.Y H:i:s', time() + 10)
        );
    }

    /** Вызывается планировщиком агентов. Пустая строка: больше не запускать. */
    public static function run($activityId = 0, $attempt = 1): string
    {
        $activityId = (int)$activityId;
        $attempt = (int)$attempt;
        $logger = Logging::get();

        try {
            $result = RecordAttacher::attach($activityId);
        } catch (\Throwable $e) {
            $logger->error('Исключение при прикреплении записи', [
                'activityId' => $activityId,
                'attempt' => $attempt,
                'exception' => $e,
            ]);

            return $attempt < Config::MAX_ATTEMPTS ? self::command($activityId, $attempt + 1) : '';
        }

        if ($result->isSuccess()) {
            $logger->info('Дело обработано', ['activityId' => $activityId, 'attempt' => $attempt] + $result->getData());

            return '';
        }

        $error = $result->getErrors()[0];
        $logger->warning('Запись не прикреплена', [
            'activityId' => $activityId,
            'attempt' => $attempt,
            'code' => $error->getCode(),
            'reason' => $error->getMessage(),
        ]);

        if ($error->getCode() === RecordAttacher::ERR_RETRY && $attempt < Config::MAX_ATTEMPTS) {
            return self::command($activityId, $attempt + 1);
        }

        return '';
    }

    private static function command(int $activityId, int $attempt): string
    {
        return '\Shef\FixMoizvonki\Agent::run(' . $activityId . ',' . $attempt . ');';
    }
}
