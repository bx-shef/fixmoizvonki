<?php

// Защита от ошибок, найденных на боевом портале 2026-11-10. Каждая найдена на реальном сбое.
$attacher = file_get_contents(dirname(__DIR__) . '/lib/recordattacher.php');
$agent = file_get_contents(dirname(__DIR__) . '/lib/agent.php');
$logging = file_get_contents(dirname(__DIR__) . '/lib/logging.php');

return [
    // HttpClient::query() с setOutputStream() тело в файл не пишет, его пишет getResult():
    // без этого скачивался пустой файл («скачан пустой файл»).
    'после setOutputStream вызывается getResult()' => fn() => str_contains($attacher, 'setOutputStream')
        && preg_match('/->query\(.*?\$http->getResult\(\);.*?fclose/s', $attacher) === 1,
    // Форматтер Битрикса подставляет только плейсхолдеры: без Logging::write деталей в логе нет.
    'агент пишет в лог только через Logging::write' => fn() => !str_contains($agent, '->warning(')
        && !str_contains($agent, '->error(') && str_contains($agent, 'Logging::write('),
    'Logging::write кладёт в текст дату и уровень' => fn() => str_contains($logging, '{date} {level} '),
    // События Add и Update приходят оба: одно дело обрабатывает один процесс.
    'обработка дела под блокировкой' => fn() => str_contains($agent, '->lock(') && str_contains($agent, '->unlock('),
];
