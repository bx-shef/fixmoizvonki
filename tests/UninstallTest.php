<?php

// Установка и удаление на заглушках ядра: после удаления модуля не должно остаться ни подписок
// на события CRM (иначе обработчик продолжал бы работать), ни его агентов, ни записи о модуле.
require_once __DIR__ . '/stub/bitrix.php';
require_once dirname(__DIR__) . '/install/index.php';

use Bitrix\Main\EventManager;
use Bitrix\Main\ModuleManager;

$reset = static function (): void {
    EventManager::$table = [];
    ModuleManager::$registered = [];
    CAgent::$removedFor = [];
};

return [
    'после установки подписаны оба события CRM, как Compatible' => function () use ($reset) {
        $reset();
        (new shef_fixmoizvonki())->DoInstall();

        return count(EventManager::$table) === 2
            && array_unique(array_values(EventManager::$table)) === ['compatible']
            && isset(ModuleManager::$registered['shef.fixmoizvonki']);
    },
    'после удаления подписок на события не остаётся' => function () use ($reset) {
        $reset();
        $module = new shef_fixmoizvonki();
        $module->DoInstall();
        $module->DoUninstall();

        return EventManager::$table === [];
    },
    'после удаления сняты агенты и запись о модуле' => function () use ($reset) {
        $reset();
        $module = new shef_fixmoizvonki();
        $module->DoInstall();
        $module->DoUninstall();

        return CAgent::$removedFor === ['shef.fixmoizvonki'] && ModuleManager::$registered === [];
    },
    'повторная установка не плодит подписки' => function () use ($reset) {
        $reset();
        $module = new shef_fixmoizvonki();
        $module->DoInstall();
        $module->DoInstall();

        return count(EventManager::$table) === 2;
    },
];
