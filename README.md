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
- Staff: `/staff`, `/staff/user/search`, `/staff/scan`, `/staff/order/create`, `/staff/order/{id}`, `/staff/order/{id}/reverse`, `/staff/promocodes`, `/staff/missions`, `/staff/reward/redeem`
- Admin: `/admin/settings`, `/admin/users`, `/admin/locations`, `/admin/promocodes`, `/admin/missions`, `/admin/exports`, `/admin/audit`

## Тестовые сценарии
1. **Регистрация/OTP**
   - `/auth` → ввод телефона → SMS OTP → `/auth/verify`.
   - Проверить rate limit: cooldown 60 сек, max/day, attempts.
2. **Создание заказа через QR**
   - В профиле открыть `/profile/qr`, скопировать token.
   - В staff `/staff/scan` вставить token → переход к заказу.
3. **Начисление/списание/реверс**
   - `/staff/order/create`: сумма, cashback_spend.
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

- Добавлен `/admin` дашборд с KPI, быстрыми ссылками и недавними пользователями.
- В профиле и шапке есть переход в админку/стафф в зависимости от роли пользователя.
