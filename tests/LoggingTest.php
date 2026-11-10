<?php

// Логгер модуля: реестр LoggerRegistry по умолчанию считает логгер выключенным (нужна опция «Y»),
// поэтому в Logging его спрашивать нельзя: лог модуля молчал бы с самого начала.
$logging = file_get_contents(dirname(__DIR__) . '/lib/logging.php');
$agent = file_get_contents(dirname(__DIR__) . '/lib/agent.php');

return [
    'Logging не зависит от LoggerRegistry' => fn() => !str_contains($logging, 'new LoggerRegistry') && !str_contains($logging, 'LoggerRegistry;'),
    'проверка реестра в фабрике отключена' => fn() => str_contains($logging, 'createById(Config::LOGGER_ID, [], false, false)'),
    'успешная обработка дела пишется в лог' => fn() => str_contains($agent, "Logging::write('info'"),
];
