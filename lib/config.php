<?php

namespace Shef\FixMoizvonki;

/** Константы модуля в одном месте. */
final class Config
{
    public const MODULE_ID = 'shef.fixmoizvonki';

    /** Сколько раз агент пробует прикрепить запись, прежде чем сдаться. */
    public const MAX_ATTEMPTS = 10;

    /** Пауза между попытками, секунды. */
    public const RETRY_INTERVAL = 60;

    /** Максимальный размер записи, байты (100 МБ). */
    public const MAX_BYTES = 104857600;

    public const LOGGER_ID = 'shef.fixmoizvonki.main';
}
