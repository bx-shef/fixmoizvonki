<?php

namespace Shef\FixMoizvonki;

final class Handler
{
    /**
     * События CRM OnActivityAdd и OnActivityUpdate (после сохранения дела). Ядро шлёт их
     * позиционными аргументами, поэтому обработчик зарегистрирован как Compatible.
     * Здесь только дешёвая проверка поля и постановка задачи: сеть в сохранении дела не трогаем.
     */
    public static function onActivityChange($id, &$fields): void
    {
        if (!is_array($fields) || !isset($fields['DESCRIPTION']) || !is_string($fields['DESCRIPTION'])) {
            return;
        }
        if (stripos($fields['DESCRIPTION'], 'moizvonki.ru') === false) {
            return;
        }

        Agent::schedule((int)$id);
    }
}
