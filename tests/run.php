<?php
// Запуск: php tests/run.php. Без зависимостей: тестируем только чистую логику.
$fail = 0;
foreach (glob(__DIR__ . '/*Test.php') as $file)
{
    foreach ((require $file) as $name => $check)
    {
        $ok = false;
        try { $ok = (bool)$check(); } catch (Throwable $e) { echo $e->getMessage() . PHP_EOL; }
        echo ($ok ? 'OK    ' : 'FAIL  ') . $name . PHP_EOL;
        $fail += $ok ? 0 : 1;
    }
}
exit($fail ? 1 : 0);
