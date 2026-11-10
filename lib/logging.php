<?php

namespace Shef\FixMoizvonki;

use Bitrix\Main\Application;
use Bitrix\Main\Diag\FileLogger;
use Bitrix\Main\Diag\LoggerFactory;
use Bitrix\Main\Diag\LoggerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * Логгер модуля через штатный LoggerFactory (PSR-3).
 *
 * Если администратор описал logger id `shef.fixmoizvonki.main` в секции `loggers`
 * файла /bitrix/.settings.php, берётся он. Иначе пишем в файл `shef.fixmoizvonki.log`
 * в каталоге `sh_log` на уровень выше корня сайта (вне публичной части).
 * Нет доступного каталога или логгер выключен в реестре: молчим, на работу это не влияет.
 */
final class Logging
{
    private static ?LoggerInterface $logger = null;

    public static function get(): LoggerInterface
    {
        if (self::$logger !== null) {
            return self::$logger;
        }

        if (!(new LoggerRegistry())->isEnabled(Config::LOGGER_ID)) {
            return self::$logger = new NullLogger();
        }

        $logger = (new LoggerFactory(false))->createById(Config::LOGGER_ID, [], true, false);
        if ($logger === null) {
            $logger = self::createDefault();
        }

        return self::$logger = $logger;
    }

    private static function createDefault(): LoggerInterface
    {
        $dir = dirname(rtrim(Application::getDocumentRoot(), '/' . chr(92))) . '/sh_log';
        if (!is_dir($dir) || !is_writable($dir)) {
            return new NullLogger();
        }

        $logger = new FileLogger($dir . '/shef.fixmoizvonki.log');
        $logger->setLevel(LogLevel::INFO);

        return $logger;
    }
}
