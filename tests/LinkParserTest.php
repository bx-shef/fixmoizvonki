<?php
// Запуск: php tests/run.php
require_once __DIR__ . '/../lib/linkparser.php';

use Shef\FixMoizvonki\LinkParser;

const DESC = '<audio src="https://rivalsvarka.moizvonki.ru/calls/recordings/gcjbIUcEHPffajOrPKyxdLzTpJvgiLnB.m4a/" style="width: 100%" controls></audio><br><br><a href="https://rivalsvarka.moizvonki.ru/calls/recordings/gcjbIUcEHPffajOrPKyxdLzTpJvgiLnB.m4a/">Скачать запись разговора</a><p>Длительность звонка: 93 сек.</p>';

return [
    'ссылка извлекается из описания' => fn() => LinkParser::extractRecordUrl(DESC)
        === 'https://rivalsvarka.moizvonki.ru/calls/recordings/gcjbIUcEHPffajOrPKyxdLzTpJvgiLnB.m4a/',
    'нет ссылки: null' => fn() => LinkParser::extractRecordUrl('<p>Длительность звонка: 5 сек.</p>') === null,
    'чужой домен не принимается' => fn() => LinkParser::extractRecordUrl('<a href="https://evil.example/calls/recordings/a.m4a">x</a>') === null,
    'домен с подвохом не принимается' => fn() => LinkParser::isAllowedUrl('https://moizvonki.ru.evil.example/calls/recordings/a.m4a') === false,
    'логин в адресе не принимается' => fn() => LinkParser::isAllowedUrl('https://x@a.moizvonki.ru/calls/recordings/a.m4a') === false,
    'http не принимается' => fn() => LinkParser::isAllowedUrl('http://a.moizvonki.ru/calls/recordings/a.m4a') === false,
    'порт не принимается' => fn() => LinkParser::isAllowedUrl('https://a.moizvonki.ru:8443/calls/recordings/a.m4a') === false,
    'чужой путь не принимается' => fn() => LinkParser::isAllowedUrl('https://a.moizvonki.ru/admin/a.m4a') === false,
    'нормальный адрес принимается' => fn() => LinkParser::isAllowedUrl('https://a.moizvonki.ru/calls/recordings/a.m4a') === true,
    'имя файла из ссылки со слешем на конце' => fn() => LinkParser::fileNameFromUrl('https://a.moizvonki.ru/calls/recordings/abc.m4a/', 'f.m4a') === 'abc.m4a',
    'имя файла: запасное, если расширения нет' => fn() => LinkParser::fileNameFromUrl('https://a.moizvonki.ru/calls/recordings/abc', 'f.m4a') === 'f.m4a',
    'CALL_ID из ORIGIN_ID' => fn() => LinkParser::callIdFromOriginId('VI_externalCall.681c.1794298183') === 'externalCall.681c.1794298183',
    'ORIGIN_ID не от внешнего звонка' => fn() => LinkParser::callIdFromOriginId('VI_abc') === null,
    'из описания убираются плеер и ссылка, длительность остаётся' => fn() => LinkParser::stripRecordBlock(DESC) === '<p>Длительность звонка: 93 сек.</p>',
    'описание без записи не меняется' => fn() => LinkParser::stripRecordBlock('<p>Привет</p>') === '<p>Привет</p>',
];
