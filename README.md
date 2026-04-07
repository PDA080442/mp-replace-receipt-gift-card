# MP Replace Receipt Gift Card (YooKassa + Robokassa)

Плагин для WooCommerce, который подменяет поля **первого чека** при покупке gift card:

- для YooKassa: `payment_mode`, `payment_subject`, описание позиции, опционально shipping;
- для Robokassa: `payment_method`, `payment_object`, имя позиции, опционально `tax` и shipping.

## Важно

- Плагин **не отправляет чеки сам**.
- Плагин **не формирует второй чек**.
- Плагин только модифицирует payload через фильтры платежных модулей перед отправкой.

## Требования

- WordPress + WooCommerce
- PHP 7.4+
- Совместимость с HPOS объявлена (`custom_order_tables`)

## Настройки

Страница: `WooCommerce -> Replace Gift Receipt`

Вкладки:
- `Common` — общие параметры и детекция gift card.
- `YooKassa` — правила подмены для YK.
- `Robokassa` — правила подмены для RB.
- `Diagnostics` — локальная диагностика (без API вызовов).

## Поля вкладки Common

### Plugin enabled

- Опция: `mp_rrgc_common_enabled`
- Что делает: включает runtime-логику подмены.
- Если выключено: подмена не выполняется, но админка доступна.
- Рекомендуемо: `включено` на бою после тестов.

### Debug log

- Опция: `mp_rrgc_debug`
- Что делает: включает логи уровня `DEBUG`.
- Рекомендуемо: `выключено` на бою, `включено` на этапе отладки.

### Gift-card detection mode

- Опция: `mp_rrgc_detection_mode`
- Значения:
  - `product_ids` — по ID товаров;
  - `category` — по категориям;
  - `meta` — по метаполю товара/вариации;
  - `product_type` — по типу товара;
  - `filter_only` — только внешняя логика через фильтр.
- Рекомендуемо: начать с `product_ids` (самый предсказуемый режим).

### Gift product IDs

- Опция: `mp_rrgc_gift_product_ids`
- Формат: CSV, например `12,34,56`.
- Используется только при `detection_mode = product_ids`.

### Gift categories

- Опция: `mp_rrgc_gift_category_ids`
- Формат: мультиселект категорий `product_cat`.
- Используется только при `detection_mode = category`.

### Gift meta key / Gift meta value

- Опции:
  - `mp_rrgc_gift_meta_key`
  - `mp_rrgc_gift_meta_value`
- Логика:
  - если задан только key: gift card = любой непустой meta value;
  - если задан key+value: gift card = точное совпадение значения.
- Используется только при `detection_mode = meta`.

### Gift product type

- Опция: `mp_rrgc_gift_product_type`
- Пример: `gift_card`
- Используется только при `detection_mode = product_type`.

### Gift-only orders only

- Опция: `mp_rrgc_only_if_order_is_gift_only`
- Что делает: обрабатывает только заказы, где **все** товарные линии gift card.
- Если включено: mixed cart будет пропущен.

### Allow mixed cart

- Опция: `mp_rrgc_allow_mixed_cart`
- Что делает: разрешает подмену, если в заказе одновременно gift + обычные товары.
- Важно: если `Gift-only orders only = on`, этот флаг фактически не поможет mixed cart.

### Allowed gateways

- Опция: `mp_rrgc_gateways`
- Что делает: ограничивает обработку только выбранными gateway ID.
- Если пусто: любой gateway.

### Hook priority

- Опция: `mp_rrgc_hook_priority`
- Диапазон: `1..9999`
- Что делает: приоритет фильтров подмены для YK/RB.
- Когда менять: если другой плагин перетирает поля после этого плагина.
- Практика: стартовать с `999`, повышать при конфликтах.

## Поля вкладки YooKassa

### YooKassa enabled

- Опция: `mp_rrgc_yk_enabled`
- Что делает: включает ветку подмены для YK.

### payment_mode

- Опция: `mp_rrgc_yk_payment_mode`
- Применяется к строкам чека согласно условиям обработки.
- Базово для gift card обычно используют `advance`.

### payment_subject

- Опция: `mp_rrgc_yk_payment_subject`
- Для gift card обычно используют `payment`.

### Description template

- Опция: `mp_rrgc_yk_description_template`
- Плейсхолдеры:
  - `%order_id%`
  - `%order_number%`
  - `%line_no%`
- Пример: `Gift card order %order_number%`.

### VAT override (optional)

- Опция: `mp_rrgc_yk_vat_code_override`
- Сейчас хранится как опция для дальнейшего расширения логики.

### Apply to shipping

- Опция: `mp_rrgc_yk_apply_to_shipping`
- Что делает: применяет подмену и к строке доставки.

### Only gift lines

- Опция: `mp_rrgc_yk_only_gift_lines`
- Что делает: если включено — меняются только gift-строки.
- Если выключено — можно менять все строки (в рамках order-level условий).

### Force override

- Опция: `mp_rrgc_yk_force_override`
- Что делает: перезаписывает поля даже если в строке уже есть значения.

### Preflight

- Основан на `validate_yk_rules()`.
- Показывает `PASS/WARN` и список проблем конфигурации.

## Поля вкладки Robokassa

### Robokassa enabled

- Опция: `mp_rrgc_rb_enabled`
- Что делает: включает ветку подмены для RB.

### payment_method

- Опция: `mp_rrgc_rb_payment_method`
- Аналог способа расчёта для Robokassa receipt.

### payment_object

- Опция: `mp_rrgc_rb_payment_object`
- Аналог предмета расчёта.

### Name template

- Опция: `mp_rrgc_rb_name_template`
- Плейсхолдеры:
  - `%order_id%`
  - `%order_number%`
  - `%line_no%`

### Tax override (optional)

- Опция: `mp_rrgc_rb_tax_override`
- Что делает: при заполнении принудительно ставит `tax` в строках.

### Apply to shipping

- Опция: `mp_rrgc_rb_apply_to_shipping`
- Что делает: применяет подмену к строке доставки.

### Only gift lines

- Опция: `mp_rrgc_rb_only_gift_lines`
- Что делает: менять только строки, определенные как gift.

### Force override

- Опция: `mp_rrgc_rb_force_override`
- Что делает: перезаписывать значения даже если поля уже заданы.

### Preflight

- Основан на `validate_rb_rules()`.
- Показывает `PASS/WARN` и список проблем.

## Поля вкладки Diagnostics

### Inspect product by ID

- AJAX: `mp_rrgc_inspect_product`
- Возвращает:
  - найден ли товар,
  - `is_gift`,
  - причины детекции (`reasons`).

### Inspect order for YooKassa / Robokassa

- AJAX:
  - `mp_rrgc_inspect_order_yk`
  - `mp_rrgc_inspect_order_rb`
- Возвращает:
  - `should_process`,
  - счетчики gift/regular,
  - preview строк (до 50),
  - текущие replacement-настройки для выбранного провайдера.

### Ограничение вывода

- В preview не выводятся лишние чувствительные данные.
- Количество строк ограничено (`max_items = 50`).

### Основные опции Common

- `Plugin enabled` — мастер-переключатель runtime.
- `Debug log` — включает DEBUG-логи.
- `Gift-card detection mode`:
  - `product_ids`
  - `category`
  - `meta`
  - `product_type`
  - `filter_only`
- `Allowed gateways` — ограничение по платежным шлюзам (пусто = любой).
- `Hook priority` — приоритет фильтров подмены.

## Режимы детекции gift card

- `product_ids` — по списку ID товаров.
- `category` — по категориям `product_cat`.
- `meta` — по `meta_key` (+ опционально `meta_value`).
- `product_type` — по типу товара.
- `filter_only` — базово false, ожидается внешняя логика через фильтр.

## Логи

Путь:

- `wp-content/uploads/mp-replace-receipt-gift-card/logs/`

Формат:

- файл: `rrgc-YYYY-MM.log`
- уровни: `DEBUG`, `INFO`, `ERROR`

Секреты и чувствительные ключи в логах маскируются.

## Поддерживаемые хуки интеграции

### Внутренние фильтры плагина

- `mp_rrgc_should_process_order`
- `mp_rrgc_is_gift_product`
- `mp_rrgc_gift_detection_reasons`
- `mp_rrgc_hook_priority`
- `mp_rrgc_yk_replaced_payload`
- `mp_rrgc_rb_replaced_payload`

### Хуки платежных плагинов, которые перехватываются

- YooKassa: `woocommerce_yookassa_create_payment_request`
- Robokassa: `wc_robokassa_receipt`

## Примеры фильтров

### 1) Кастомная детекция gift card

```php
add_filter('mp_rrgc_is_gift_product', function ($is_gift, $product) {
	if (! $product instanceof WC_Product) {
		return $is_gift;
	}

	// Пример: считать gift card товары с префиксом SKU "GIFT-"
	$sku = (string) $product->get_sku();
	if ($sku !== '' && strpos($sku, 'GIFT-') === 0) {
		return true;
	}

	return $is_gift;
}, 10, 2);
```

### 2) Тонкая правка YooKassa payload после подмены

```php
add_filter('mp_rrgc_yk_replaced_payload', function ($payment_request, $order, $context) {
	// Здесь можно добавить проектную логику поверх уже измененного payload.
	return $payment_request;
}, 10, 3);
```

### 3) Тонкая правка Robokassa payload после подмены

```php
add_filter('mp_rrgc_rb_replaced_payload', function ($receipt, $order, $context) {
	// Пример: если нужно добавить свою служебную метку в item name.
	return $receipt;
}, 10, 3);
```

## Troubleshooting

- **Подмена не срабатывает**
  - Проверьте `Plugin enabled`.
  - Проверьте, что включена нужная ветка (`YooKassa`/`Robokassa`).
  - Проверьте `Allowed gateways`.
  - На вкладке `Diagnostics` проверьте `should_process`.

- **Конфликт с другими receipt-плагинами**
  - На странице плагина показывается compatibility notice.
  - Попробуйте увеличить `Hook priority`.

- **Неверно определяется gift card**
  - Проверьте `Gift-card detection mode`.
  - Используйте `Inspect product` в Diagnostics.
  - При необходимости подключите фильтр `mp_rrgc_is_gift_product`.

## I18N

- Text domain: `mp-replace-receipt-gift-card`
- Translations path: `languages/`