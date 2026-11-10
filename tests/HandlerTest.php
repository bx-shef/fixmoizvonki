<?php

// Обработчик и агент без ядра: подменяем только CAgent и записываем, что в него передали.
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/handler.php';
require_once __DIR__ . '/../lib/agent.php';

if (!class_exists('CAgent')) {
    class CAgent
    {
        public static array $calls = [];

        public static function AddAgent(...$args)
        {
            self::$calls[] = $args;

            return 1;
        }
    }
}

use Shef\FixMoizvonki\Handler;

$call = static function (string $description): int {
    CAgent::$calls = [];
    $fields = ['DESCRIPTION' => $description];
    Handler::onActivityChange(1462077, $fields);

    return count(CAgent::$calls);
};

return [
    'описание со ссылкой моих звонков ставит агента' => fn() => $call('<a href="https://x.moizvonki.ru/calls/recordings/a.m4a/">r</a>') === 1,
    'описание без ссылки агента не ставит' => fn() => $call('<p>Длительность звонка: 5 сек.</p>') === 0,
    'правка дела без описания агента не ставит' => function () {
        CAgent::$calls = [];
        $fields = ['SUBJECT' => 'x'];
        Handler::onActivityChange(1, $fields);

        return CAgent::$calls === [];
    },
    'агент ставится на модуль shef.fixmoizvonki и на нужное дело' => function () use ($call) {
        $call('moizvonki.ru');

        return CAgent::$calls[0][1] === 'shef.fixmoizvonki'
            && str_contains(CAgent::$calls[0][0], 'Agent::run(1462077,1);');
    },
];
