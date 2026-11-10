<?php

namespace Shef\FixMoizvonki;

/**
 * Чистая логика без зависимостей от Битрикса: разбор описания дела «Мои звонки».
 * Тесты: tests/LinkParserTest.php
 */
final class LinkParser
{
    private const URL_PATTERN = '~https://[a-z0-9.-]+\.moizvonki\.ru/calls/recordings/[^"\'<>\s]+~i';

    /** Ссылка на запись из описания дела или null, если её нет или она не с нашего домена. */
    public static function extractRecordUrl(string $description): ?string
    {
        if (!preg_match(self::URL_PATTERN, $description, $m))
        {
            return null;
        }
        $url = html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5);

        return self::isAllowedUrl($url) ? $url : null;
    }

    /**
     * Защита от подмены адреса (SSRF): только https, домен moizvonki.ru и его поддомены,
     * без логина и порта, путь /calls/recordings/.
     */
    public static function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https')
        {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['port']))
        {
            return false;
        }
        $host = strtolower($parts['host'] ?? '');
        if (!preg_match('~^([a-z0-9-]+\.)+moizvonki\.ru$~', $host))
        {
            return false;
        }

        return str_starts_with($parts['path'] ?? '', '/calls/recordings/');
    }

    /** Имя файла из ссылки; если нормального имени нет, берём запасное. */
    public static function fileNameFromUrl(string $url, string $fallback): string
    {
        $path = rtrim((string)parse_url($url, PHP_URL_PATH), '/');
        $name = basename($path);
        if ($name === '' || !preg_match('~^[A-Za-z0-9._-]+\.[A-Za-z0-9]{2,5}$~', $name))
        {
            return $fallback;
        }

        return $name;
    }

    /** CALL_ID из ORIGIN_ID дела: «VI_externalCall.<hash>.<time>» даёт «externalCall.<hash>.<time>». */
    public static function callIdFromOriginId(string $originId): ?string
    {
        if (!str_starts_with($originId, 'VI_externalCall.'))
        {
            return null;
        }

        return substr($originId, 3);
    }

    /** Убирает из описания плеер и ссылку на запись, остальное (длительность) оставляет. */
    public static function stripRecordBlock(string $description): string
    {
        $out = preg_replace('~<audio\b[^>]*>.*?</audio>~is', '', $description) ?? $description;
        $out = preg_replace('~<a\b[^>]*moizvonki\.ru/calls/recordings[^>]*>.*?</a>~is', '', $out) ?? $out;
        $out = preg_replace('~^(\s|<br\s*/?>)+~i', '', $out) ?? $out;

        return trim($out);
    }
}
