<?php

// Обработчик без ядра (заглушки из tests/stub/bitrix.php): фоновая задача ставится только для
// описания со ссылкой «Мои звонки»; не вышло поставить задачу: запасной вариант, агент.
require_once __DIR__ . '/stub/bitrix.php';
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/handler.php';
require_once __DIR__ . '/../lib/agent.php';

use Bitrix\Main\Application;
use Shef\FixMoizvonki\Handler;

$call = static function (string $description, bool $jobThrows = false): void {
    Application::$jobs = [];
    Application::$jobThrows = $jobThrows;
    CAgent::$calls = [];
    $fields = ['DESCRIPTION' => $description];
    Handler::onActivityChange(1462077, $fields);
    Application::$jobThrows = false;
};

return [
    'описание со ссылкой: фоновая задача ставится сразу, агент не нужен' => function () use ($call) {
        $call('<a href="https://x.moizvonki.ru/calls/recordings/a.m4a/">r</a>');

        return count(Application::$jobs) === 1 && CAgent::$calls === []
            && Application::$jobs[0][0] === [\Shef\FixMoizvonki\Agent::class, 'runNow']
            && Application::$jobs[0][1] === [1462077];
    },
    'описание без ссылки: ничего не ставится' => function () use ($call) {
        $call('<p>Длительность звонка: 5 сек.</p>');

        return Application::$jobs === [] && CAgent::$calls === [];
    },
    'правка дела без описания: ничего не ставится' => function () {
        Application::$jobs = [];
        CAgent::$calls = [];
        $fields = ['SUBJECT' => 'x'];
        Handler::onActivityChange(1, $fields);

        return Application::$jobs === [] && CAgent::$calls === [];
    },
    'не вышло поставить фоновую задачу: запасной агент на то же дело' => function () use ($call) {
        $call('moizvonki.ru', true);

        return Application::$jobs === [] && count(CAgent::$calls) === 1
            && CAgent::$calls[0][1] === 'shef.fixmoizvonki'
            && str_contains(CAgent::$calls[0][0], 'Agent::run(1462077,1);');
    },
];
