# Coffee Loyalty PWA (PHP + MySQL, Beget-ready)

PWA-приложение для кофейни Kapouch: loyalty (штампы), cashback, OTP-авторизация по телефону (sms.ru), промокоды, рефералы, миссии, staff/admin зоны, audit, CSV-экспорт.\n\n- Название кофейни: **Kapouch**\n- Адрес: **Шелехов, Култукский тракт 25/1**

## Стек
- PHP 8.x (без фреймворков)
- MySQL InnoDB + PDO prepared statements
- Vanilla HTML/CSS/JS
- PHP sessions + secure cookie params
- PWA: `manifest.json`, `service-worker.js`, offline fallback

## Структура
- `public/` — исходная web-папка (сохранена для совместимости)
- `index.php` (в корне) — точка входа для хостинга
- `app/controllers/` — контроллеры
- `app/lib/` — сервисы (RulesEngine, Ledger, FraudGuard, QrToken, SmsRuClient, etc.)
- `app/views/` — шаблоны
- `database/schema.sql` — схема БД
- `storage/logs/app.log` — лог приложения

## Деплой на Beget
1. Создайте БД и пользователя в панели Beget.
2. Импортируйте `database/schema.sql`.
3. Скопируйте `config.php.example` в `config.php`, заполните DB/sms.ru/app secret.
4. Корень сайта оставьте на корне проекта (требование хостинга), т.к. `index.php` лежит в корне.
5. Убедитесь, что `storage/logs/app.log` доступен на запись.
6. Создайте admin:
   - зарегистрируйте пользователя через OTP,
   - затем SQL: `UPDATE users SET role='admin' WHERE phone='+7XXXXXXXXXX';`

## Важно для хостинга
- Точка входа: корневой `index.php`.
- Статические файлы доступны из корня: `/assets`, `/manifest.json`, `/service-worker.js`.
- Для Apache добавлен `.htaccess` с rewrite в `index.php`.

## Базовые URL
- Публичные: `/`, `/auth`, `/auth/verify`, `/r/{refcode}`, `/logout`
- Пользователь: `/profile`, `/profile/qr`, `/profile/invite`, `/profile/phone-change`, `/profile/birthday`, `/history`
- Staff: `/staff`, `/staff/user/search`, `/staff/scan`, `/staff/order/create`, `/staff/order/{id}`, `/staff/order/{id}/reverse`, `/staff/orders/live`, `/staff/promocodes`, `/staff/missions`, `/staff/reward/redeem`
- Admin: `/admin`, `/admin/settings`, `/admin/users`, `/admin/locations`, `/admin/promocodes`, `/admin/missions`, `/admin/push`, `/admin/data`, `/admin/exports`, `/admin/audit`

## Тестовые сценарии
1. **Регистрация/OTP**
   - `/auth` → ввод телефона → SMS OTP → `/auth/verify`.
   - Проверить rate limit: cooldown 60 сек, max/day, attempts.
2. **Создание заказа через QR**
   - В профиле открыть `/profile/qr`, скопировать token.
   - В staff `/staff/scan` вставить token → переход к заказу.
3. **Начисление/списание/реверс**
   - `/staff/order/create`: код клиента (user_id), сумма заказа, количество штампов.
   - Проверить ledger + `/staff/order/{id}` → reversal.
4. **Промокод/реферал/миссия**
   - Создать promocode в БД и применить в заказе.
   - Зарегистрировать нового пользователя через `/r/{refcode}` и сделать первый заказ.
   - Создать mission в БД, сделать нужное количество заказов.
5. **CSV экспорт**
   - `/admin/exports?type=orders|operations|users`.

## Примечания
- OTP хранится как HMAC hash, срок жизни и лимиты настраиваемые.
- Финансовые операции только через ledger + reversal записи.
- Антифрод лимиты проверяются централизованно.
- В проекте есть задел под optional модули (preorder flag, External provider interface-заготовка можно расширить).


## Дополнительно реализовано
- Идемпотентность создания заказа через `idempotency_key`.
- Staff-сценарий списания награды 6/6.
- Admin CRUD-экраны для промокодов и миссий.
- Опциональная одноразовость QR (через `features.qr_nonce_single_use` + `qr_nonces`).
- In-app уведомления в профиле и camera QR decode через BarcodeDetector (где поддерживается).


## UX-обновление
- Переработан мобильный дизайн интерфейса (карточки, CTA, типографика).
- `Пригласить друга` ведёт на отдельную страницу `/profile/invite` с share-ссылкой и QR.
- `Мой QR` отображается в виде QR-изображения + есть fallback токен.

- Интерфейс обновлён под фирменный стиль Kapouch (жёлто-коричневая палитра, бренд-логотип в шапке).
- В PWA добавлено фиксированное нижнее меню навигации (как в мобильных браузерах): разделы всегда доступны у нижней кромки экрана.
- В меню добавлены быстрый поиск по позициям и кнопка «Повторить прошлый заказ» (восстановление последней оплаченной корзины).
- Добавлен engagement-пакет в меню: дневная серия, бонус дня, лаки-позиция, недавние просмотры, share корзины, динамическая оценка времени и умные upsell-подсказки.

- Добавлен `/admin` дашборд с KPI, быстрыми ссылками и недавними пользователями.
- В профиле и шапке есть переход в админку/стафф в зависимости от роли пользователя.

- На экране входа добавлена маска телефона `+7 (___) ___-__-__`.
- В админке добавлен раздел уведомлений в профиль (`/admin/push`) и универсальный менеджер данных (`/admin/data`) для редактирования таблиц.

- В рассылках уведомлений добавлена сегментация аудитории по ролям (all/user/barista/manager/admin) и отображение размера аудиторий.
- В `/admin/data` добавлены поиск по текстовым колонкам, выбор лимита выдачи и экспорт текущей выборки в CSV.
- В клиентской ленте добавлена кнопка `Отметить уведомления прочитанными` (bulk read).


- Добавлена публичная страница меню `/menu`.
- Добавлен раздел админки `/admin/menu` для управления позициями меню (название, цена, описание, картинка, сортировка, активность).
- Реализованы модификаторы для товаров: группы опций (single/multi), наценка за модификатор, обязательные группы, временный стоп-лист модификаторов и управление всем этим в `/admin/menu`.
- Для QR реализованы короткие коды (8 символов) для быстрого ввода бариста, длинный токен оставлен как резервный.
- Начисление штампов при создании заказа переведено на явный ввод количества штампов в форме staff.
- В кампаниях уведомлений добавлен расчёт фактически созданных уведомлений (`recipients_count`) и статистика `sent/read` по каждой кампании.


- В меню добавлены категории и режим стоп-листа (скрытие/показ в публичном разделе).
- Уведомления в профиль получили шаблоны и отложенную отправку.
- На админ-дашборде добавлен блок health-метрик (OTP, уведомления, непрочитанные уведомления, стоп-лист).


- Короткий код клиента переведён на формат из 5 цифр, с автоматической ротацией раз в 5–7 минут.
- Раздел `/profile/qr` адаптирован под короткий код для начисления штампов (без QR-картинки и длинного токена).
- В истории профиля статусы заказов и типы операций кэшбэка отображаются на русском языке.
- В шапке убран графический логотип, оставлено текстовое название `KAPOUCH/`.
- Избранное в меню синхронизируется с сервером для авторизованных пользователей (`/api/menu/favorites`, `/api/menu/favorites/toggle`) и хранится локально как fallback.
- Добавлена таблица `user_menu_favorites` в `database/schema.sql`.

- Добавлена интеграция с AQSI через API (`/api/staff/aqsi/check`) для сервисных сценариев.

## Интеграция с кассой AQSI
1. Заполните в `config.php` блок:
   - `aqsi.base_url` (обычно `https://api.aqsi.ru`)
   - `aqsi.api_token` (токен API из кабинета AQSI)
   - `aqsi.receipt_path` (по умолчанию `/v1/receipts/{id}`)
   - `aqsi.order_path` (fallback, по умолчанию `/v1/orders/{id}`)
2. Endpoint можно использовать для внутренних интеграций и автоматизации (`receipt` с fallback на `order`).
3. Текущий staff-экран начисления упрощён до: код клиента, сумма заказа, количество штампов.


## Уведомления в профиле
- В проекте используются только внутренние уведомления в профиле (без браузерного Web Push).
- Админка `/admin/push` отправляет пользователю карточку: заголовок, текст и изображение (по URL).
- Лента на `/profile` обновляется polling-запросами и позволяет отмечать уведомления прочитанными.

### Пример для обычного хостинга Beget (shared)
1. Включите SSH-доступ в панели Beget и подключитесь:
   - `ssh <user>@<server>`
2. Перейдите в папку сайта (обычно `~/sites/<domain>/` или `~/www/<domain>/`).
3. Установите зависимости:
   - Если Composer доступен: `composer install --no-dev --optimize-autoloader`
   - Если Composer нет, установите локально и запустите:
     - `php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"`
     - `php composer-setup.php`
     - `php -r "unlink('composer-setup.php');"`
     - `php composer.phar install --no-dev --optimize-autoloader`
4. Убедитесь, что веб-корень направлен в папку `public/` (в панели Beget это «корневая папка сайта»).  
   Если изменить корень нельзя, перенесите содержимое `public/` в корневую папку сайта.
5. Где запускать Composer:
   - Запускайте `composer install` из папки домена, где лежит `composer.json` (обычно `~/sites/<domain>/` или `~/www/<domain>/`).
   - В `public_html` запускать не нужно, если она указывает на `public/`. `vendor/` должен находиться рядом с `composer.json`, а не внутри `public_html`.
6. Права на запись:
   - Проверьте, что папка `storage/logs/` доступна на запись для PHP.
7. HTTPS:
   - Включите SSL-сертификат в панели Beget, иначе PWA (Service Worker) и онлайн-оплата будут работать нестабильно.


## Оплата в PWA через СБП Т‑Банк (Tinkoff)
1. Заполните в `config.php` блок:
   - `tinkoff.base_url` (обычно `https://securepay.tinkoff.ru/v2`)
   - `tinkoff.terminal_key`
   - `tinkoff.password`
   - `tinkoff.notification_url` (URL для webhook, например `https://kapouch.ru/api/payments/tinkoff/notify`)
   - `tinkoff.receipt_enabled` (1/0, для Т‑Чеков)
   - `tinkoff.receipt_taxation` (например `usn_income`)
   - `tinkoff.receipt_vat` (например `none`)
   - `tinkoff.receipt_payment_object`
   - `tinkoff.receipt_payment_method`
   - `tinkoff.receipt_email` (если не хотите передавать телефон клиента)
2. Пользователь открывает `/menu`, добавляет позиции в корзину и жмёт `Оплатить через СБП Т‑Банк`.
3. Бэкенд пересчитывает сумму по актуальным ценам `menu_items`, создаёт платёж через `Init` и возвращает ссылку оплаты.
4. Клиент перенаправляется на платёжную страницу СБП.

5. Если у вас включены Т‑Чеки, backend теперь отправляет объект `Receipt` в `Init` (позиции, налогообложение, НДС, метод/предмет расчёта).

Технически:
- endpoint: `POST /api/checkout/sbp`;
- webhook от Т‑Банка: `POST /api/payments/tinkoff/notify` (обновляет статус в `payment_sessions`);
- сохраняется сессия оплаты в `payment_sessions`;
- для оплаты требуется авторизация пользователя.


- Лента заказов в реальном времени для бариста: `/staff/orders/live` + API `/api/staff/orders/live` и смена статуса через `/api/staff/orders/live/status`. В ленту попадают только успешно оплаченные заказы (статусы `accepted`, `preparing`, `ready`, `done`).

Если при оплате вы видите «не удалось создать платёж», теперь API возвращает точную причину: не настроены ключи (`config_missing`) или ошибка провайдера (`provider_error`).
