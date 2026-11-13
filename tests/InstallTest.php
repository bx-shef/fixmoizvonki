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
            preg_match_all("/getMessage\('([A-Za-z0-9_.]+)'/", file_get_contents(dirname(__DIR__) . '/' . $file), $m);
            $missing = array_merge($missing, array_diff($m[1], $keys));
        }
        if ($missing) {
            echo '  нет перевода: ' . implode(', ', $missing) . "\n";
        }

        return !$missing;
    },
    'название с префиксом [SH], партнёр и сайт как у линейки' => fn() => str_starts_with($MESS['shef.fixmoizvonki_MODULE_NAME'], '[SH] ')
        && $MESS['shef.fixmoizvonki_PARTNER_NAME'] === 'ООО «БэИксШефБай»'
        && $MESS['shef.fixmoizvonki_PARTNER_URI'] === 'https://bx-shef.by',
    'сортировка в ряду shef.* (options 1000, uiclear 1001, problems 1002)' => fn() => str_contains($install, 'MODULE_SORT = 1003'),
    'при удалении снимаются агенты модуля' => fn() => str_contains($install, 'RemoveModuleAgents'),
];
