<?php

// Отступы пробелами (PSR-12): табов в php-файлах нет. Имена в lib/ только строчные: на Linux иначе класс не найдётся.
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__), FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() === 'php' && !str_contains($f->getPathname(), DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        $files[] = $f->getPathname();
    }
}

return [
    'в php-файлах нет табов' => function () use ($files) {
        foreach ($files as $file) {
            if (str_contains(file_get_contents($file), "\t")) {
                echo "  таб: $file\n";

                return false;
            }
        }

        return true;
    },
    'файлы в lib/ названы строчными буквами' => function () {
        foreach (glob(dirname(__DIR__) . '/lib/*.php') as $file) {
            if (basename($file) !== strtolower(basename($file))) {
                return false;
            }
        }

        return true;
    },
    'каждый php-файл проходит синтаксис' => function () use ($files) {
        foreach ($files as $file) {
            exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
            if ($code !== 0) {
                echo "  синтаксис: $file\n";

                return false;
            }
        }

        return true;
    },
];
