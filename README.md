# Notification Service (Laravel 11)

REST API сервис для отправки уведомлений по каналам `email` и `telegram` (через заглушки), отслеживания статуса доставки, просмотра истории и асинхронной генерации CSV-отчетов.

## Технологии

- PHP 8.2, Laravel 11
- MySQL 8.4
- Redis 7 (queue + cache)
- Docker / Docker Compose
- PHPUnit, PHPStan (level 5), Laravel Pint

## Локальный запуск через Docker

1) Поднять контейнеры:

```bash
docker compose up -d --build
```

2) Установить зависимости внутри `app` контейнера:

```bash
docker compose exec app composer install
```

3) Подготовить окружение:

```bash
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate
```

4) Применить миграции:

```bash
docker compose exec app php artisan migrate
```

5) Запустить queue worker (в отдельном терминале):

```bash
docker compose exec app php artisan queue:work --tries=5
```

API будет доступен на `http://localhost:8080`.

## API

### 1) Создать уведомление

`POST /api/notifications`

Body:

```json
{
  "user_id": 10,
  "channel": "email",
  "message": "Hello from API"
}
```

Response `201`:

```json
{
  "id": 1,
  "status": "processing"
}
```

### 2) Получить статус уведомления

`GET /api/notifications/{id}/status`

Response `200`:

```json
{
  "id": 1,
  "status": "sent",
  "attempts": 1,
  "error_message": null,
  "sent_at": "2026-05-08T05:00:00.000000Z"
}
```

### 3) История уведомлений пользователя

`GET /api/users/{userId}/notifications`

Поддерживаемые query-параметры:

- `status`: `processing|sent|error`
- `channel`: `email|telegram`
- `date_from`: `YYYY-MM-DD`
- `date_to`: `YYYY-MM-DD`
- `per_page`: `1..100`

Пример:

```bash
curl "http://localhost:8080/api/users/10/notifications?status=error&channel=email&per_page=10"
```

### 4) Создать запрос на отчет

`POST /api/reports`

Body:

```json
{
  "user_id": 10,
  "date_from": "2026-05-01",
  "date_to": "2026-05-08"
}
```

Response `201`:

```json
{
  "id": 1,
  "status": "pending"
}
```

### 5) Статус генерации отчета

`GET /api/reports/{id}/status`

Response `200`:

```json
{
  "id": 1,
  "status": "ready",
  "file_path": "reports/report_1_20260508_130000.csv",
  "error_message": null
}
```

### 6) Скачать готовый отчет

`GET /api/reports/{id}/download`

- `200` + CSV файл, если статус `ready`
- `409`, если отчет еще не готов
- `404`, если файл отчета не найден

## Очереди и гарантия доставки

- `SendNotificationJob`: `tries=5`, `backoff=[5,15,30,60]`.
- При ошибках каналов задача переотправляется до исчерпания попыток.
- После исчерпания попыток статус уведомления переводится в `error`, заполняется `error_message`.
- Успешная отправка фиксирует `status=sent`, `sent_at`, `attempts`.
- Для отчетов используется аналогичная модель состояний: `pending -> processing -> ready|error`.

## Отчет

Генерируется CSV файл в `storage/app/reports` с колонками:

`channel,total,processing,sent,error,date_from,date_to,generated_at`

## Качество кода

Команды:

```bash
php artisan test
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/pint --test
```

Composer shortcuts:

```bash
composer test
composer analyse
composer lint
```

## Финальная проверка сценариев (E2E чек-лист)

1. Создать уведомление через `POST /api/notifications` и получить `status=processing`.
2. Убедиться, что worker обработал задачу и статус по `GET /api/notifications/{id}/status` стал `sent` (или `error` при падении канала).
3. Проверить фильтрацию истории по `status/channel/date`.
4. Создать отчет через `POST /api/reports` и дождаться `status=ready`.
5. Скачать CSV через `GET /api/reports/{id}/download`.
6. Прогнать качество: тесты + phpstan + pint.

## Принятые решения

- Каналы доставки сделаны через стратегию (`NotificationChannelInterface` + `EmailChannel`/`TelegramChannel`).
- Доставка и отчеты вынесены в отдельные очереди jobs для асинхронности.
- Retry/backoff реализованы на уровне jobs для контролируемой повторной отправки.
- Отчеты хранятся на локальном диске (`local`) как CSV для простого и прозрачного экспорта.

## Что улучшить в production

- Подключить реальные провайдеры доставки (SMTP/API Telegram) вместо заглушек.
- Добавить idempotency keys для защиты от дублей.
- Включить DLQ/alerting/метрики по очередям и ошибкам каналов.
- Добавить авторизацию и ограничения доступа к истории/отчетам.
- Добавить object storage (S3-compatible) и TTL/retention для файлов отчетов.
