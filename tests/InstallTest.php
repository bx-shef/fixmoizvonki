<?php

// Установщик: обработчики CRM ставятся как Compatible (ядро шлёт позиционные аргументы),
// снимаются теми же событиями, а у каждой подписи есть перевод.
$install = file_get_contents(dirname(__DIR__) . '/install/index.php');

$mess = [];
include dirname(__DIR__) . '/lang/ru/install/index.php';
$keys = array_keys($MESS);

return [
    'обработчики событий CRM регистрируются через Compatible' => fn() => str_contains($install, 'registerEventHandlerCompatible')
        && !preg_match('/->registerEventHandler\(/', $install),
    'события установки и удаления совпадают' => fn() => str_contains($install, "'OnActivityAdd', 'OnActivityUpdate'")
        && str_contains($install, 'unRegisterEventHandler'),
    'у каждого Loc::getMessage есть перевод' => function () use ($keys) {
        $missing = [];
        foreach (['install/index.php', 'install/error.php'] as $file) {
            preg_match_all("/getMessage\('([A-Z0-9_]+)'/", file_get_contents(dirname(__DIR__) . '/' . $file), $m);
            $missing = array_merge($missing, array_diff($m[1], $keys));
        }
        if ($missing) {
            echo '  нет перевода: ' . implode(', ', $missing) . "\n";
        }

        return !$missing;
    },
    'при удалении снимаются агенты модуля' => fn() => str_contains($install, 'RemoveModuleAgents'),
];
