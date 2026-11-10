# Памятка агенту: shef.fixmoizvonki

Модуль Битрикс24 «коробки». Скачивает запись из «Мои звонки» и прикрепляет к звонку и делу.
**Автономный**: не зависит от `shef.options` и `shef.problems` (решение владельца, пока линейку
не переписали). Не добавлять такие зависимости.

- Ставится в `bitrix/modules/shef.fixmoizvonki/` (не в `local/modules`).
- Перед кодом с API Битрикса: поиск по `.bitrix-docs/framework-docs` и скиллы
  `bitrix-best-practice-core` / `bitrix-best-practice-sql`; не выдумывать классы и методы.
  Если документация и ядро расходятся, прав ядро; расхождение отметить в ответе.
- События CRM `OnActivityAdd/Update` шлются позиционно: подписка только `registerEventHandlerCompatible`.
- Скачивание: `HttpClient` с `privateIp => false`, адрес только из `LinkParser::isAllowedUrl`.
- Логи только через `Logging::get()` (PSR-3, `LoggerFactory`), без секретов и без полной ссылки на запись.
- Отступы 4 пробела (PSR-12), файлы в `lib/` строчными.
- Проверка перед PR: `php tests/run.php`. Скачивание и прикрепление проверяются на портале.
