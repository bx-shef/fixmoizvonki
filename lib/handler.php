<?php

namespace Shef\FixMoizvonki;

use Bitrix\Main\Application;

final class Handler
{
    /**
     * События CRM OnActivityAdd и OnActivityUpdate (после сохранения дела). Ядро шлёт их
     * позиционными аргументами, поэтому обработчик зарегистрирован как Compatible.
     *
     * Сеть в самом сохранении дела не трогаем: обработка уходит в фоновую задачу, которая
     * выполняется сразу после ответа (без ожидания cron). Не получилось поставить задачу:
     * запасной вариант, агент.
     */
    public static function onActivityChange($id, &$fields): void
    {
        if (!is_array($fields) || !isset($fields['DESCRIPTION']) || !is_string($fields['DESCRIPTION'])) {
            return;
        }
        if (stripos($fields['DESCRIPTION'], 'moizvonki.ru') === false) {
            return;
        }

        $id = (int)$id;
        if ($id <= 0) {
            return;
        }

        try {
            Application::getInstance()->addBackgroundJob([Agent::class, 'runNow'], [$id]);
        } catch (\Throwable $e) {
            Agent::schedule($id);
        }
    }
}
