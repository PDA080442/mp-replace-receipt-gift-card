# MP Replace Receipt Gift Card (YooKassa + Robokassa)

Плагин для WooCommerce, который подменяет поля **первого чека** при продаже gift card.

## Что делает плагин

- для YooKassa меняет поля `payment_mode`, `payment_subject`, описание строки, при необходимости строку доставки;
- для Robokassa меняет `payment_method`, `payment_object` и при необходимости строку доставки.

## Что плагин НЕ делает

- не отправляет чеки сам;
- не формирует второй чек;
- не меняет статусы заказов.

Плагин работает как “прослойка”: получает payload чека через фильтры платежных модулей, изменяет его и возвращает обратно.

## Требования

- WordPress + WooCommerce;
- PHP 7.4+;
- HPOS поддерживается (заявлена совместимость `custom_order_tables`).

## Где настройки

`WooCommerce -> Gift Card: подмена чека`

Вкладки:
- `Общие`
- `YooKassa`
- `Robokassa`
- `Диагностика`

---

## Поля админки: подробно

### Вкладка «Общие»

- `Плагин включен` (`mp_rrgc_common_enabled`) — включает рабочую логику подмены.
- `Debug-лог` (`mp_rrgc_debug`) — пишет подробные DEBUG-записи в лог.
- `Режим определения gift card` (`mp_rrgc_detection_mode`) — как плагин понимает, что товар является gift card:
  - `product_ids` — по ID товаров;
  - `category` — по категориям;
  - `meta` — по метаполю;
  - `product_type` — по типу товара;
  - `filter_only` — только через внешний фильтр.
- `ID gift-товаров` (`mp_rrgc_gift_product_ids`) — список ID через запятую, работает в режиме `product_ids`.
- `Категории gift card` (`mp_rrgc_gift_category_ids`) — мультиселект категорий, работает в режиме `category`.
- `Meta key gift card` (`mp_rrgc_gift_meta_key`) — ключ метаполя, работает в режиме `meta`.
- `Meta value gift card (опционально)` (`mp_rrgc_gift_meta_value`) — если заполнено, требуется точное совпадение значения.
- `Тип gift-товара` (`mp_rrgc_gift_product_type`) — работает в режиме `product_type`.
- `Только gift-заказы` (`mp_rrgc_only_if_order_is_gift_only`) — обрабатывать только заказы, где нет обычных товаров.
- `Разрешить mixed cart` (`mp_rrgc_allow_mixed_cart`) — разрешить обработку смешанных корзин (gift + обычные).
- `Приоритет hook` (`mp_rrgc_hook_priority`) — приоритет фильтров подмены; увеличивайте, если другой плагин перезаписывает значения после этого.

### Вкладка «YooKassa»

- `YooKassa включена` (`mp_rrgc_yk_enabled`) — активирует ветку подмены для YooKassa.
- `payment_mode` (`mp_rrgc_yk_payment_mode`) — способ расчета для строк.
- `payment_subject` (`mp_rrgc_yk_payment_subject`) — предмет расчета для строк.
- `Шаблон описания` (`mp_rrgc_yk_description_template`) — шаблон описания строки.
  - Доступные плейсхолдеры: `%order_id%`, `%order_number%`, `%line_no%`.
- `Применять к shipping` (`mp_rrgc_yk_apply_to_shipping`) — менять и строку доставки.
- `Только gift-строки` (`mp_rrgc_yk_only_gift_lines`) — менять только строки gift card.
- `Принудительная подмена` (`mp_rrgc_yk_force_override`) — перезаписывать поля, даже если уже заполнены.
- `Предпроверка` — показывает корректность конфигурации (`OK` / `ВНИМАНИЕ`).

### Вкладка «Robokassa»

- `Robokassa включена` (`mp_rrgc_rb_enabled`) — активирует ветку подмены для Robokassa.
- `payment_method` (`mp_rrgc_rb_payment_method`) — способ расчета.
- `payment_object` (`mp_rrgc_rb_payment_object`) — предмет расчета.
- `Применять к shipping` (`mp_rrgc_rb_apply_to_shipping`) — менять строку доставки.
- `Только gift-строки` (`mp_rrgc_rb_only_gift_lines`) — менять только gift-строки.
- `Принудительная подмена` (`mp_rrgc_rb_force_override`) — перезаписывать поля, даже если уже заполнены.
- `Предпроверка` — показывает корректность конфигурации (`OK` / `ВНИМАНИЕ`).

### Вкладка «Диагностика»

- `Проверить товар по ID` — показывает:
  - найден ли товар;
  - `is_gift` (определился ли как gift card);
  - причины определения (`reasons`).
- `Проверить заказ YK` / `Проверить заказ RB` — показывает:
  - `should_process`;
  - количество gift/regular строк;
  - preview строк (до 50);
  - активные настройки замены для выбранного провайдера.

---

## Логи

Путь:
- `wp-content/uploads/mp-replace-receipt-gift-card/logs/`

Формат:
- файл: `rrgc-YYYY-MM.log`
- уровни: `DEBUG`, `INFO`, `ERROR`

Секреты/ключи в логах маскируются.

---

## Основные хуки

Внутренние:
- `mp_rrgc_should_process_order`
- `mp_rrgc_is_gift_product`
- `mp_rrgc_gift_detection_reasons`
- `mp_rrgc_hook_priority`
- `mp_rrgc_yk_replaced_payload`
- `mp_rrgc_rb_replaced_payload`

Хуки платежных плагинов, которые перехватываются:
- YooKassa: `woocommerce_yookassa_create_payment_request`
- Robokassa: `wc_robokassa_receipt`

---

## Быстрый старт

1. Включите плагин.
2. На вкладке `Общие` выберите режим определения gift card (рекомендуется `product_ids`).
3. На вкладке `YooKassa` задайте `payment_mode` + `payment_subject` и включите ветку.
4. На вкладке `Robokassa` задайте `payment_method` + `payment_object` и включите ветку.
5. На вкладке `Диагностика` проверьте товар и тестовый заказ.
6. Сделайте тестовый платеж и проверьте лог.

---

## Troubleshooting

- Подмена не срабатывает:
  - проверьте `Плагин включен`;
  - включена ли нужная ветка (`YooKassa`/`Robokassa`);
  - проверьте `should_process` на вкладке `Диагностика`.

- Конфликт с другими receipt-плагинами:
  - появится предупреждение совместимости в админке;
  - увеличьте `Приоритет hook`.

- Неверно определяется gift card:
  - проверьте `Режим определения gift card`;
  - используйте диагностику товара;
  - при необходимости подключите фильтр `mp_rrgc_is_gift_product`.

---

## I18N

- Text domain: `mp-replace-receipt-gift-card`
- Папка переводов: `languages/`