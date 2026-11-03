# SCORM LMS

Минимальная LMS на Yii2 с поддержкой SCORM 1.2 и SCORM 2004: загрузка пакетов, запуск курсов в изолированном плеере, сохранение прогресса.

## Архитектура

Приложение работает на трёх origin. Недоверенный контент курсов никогда не выполняется на origin, где есть данные или API.

| Сервис | Адрес по умолчанию | Назначение |
| --- | --- | --- |
| main-app | `http://app.local:8090` | API для клиентов, демо-интерфейс (по `DEMO_UI_ENABLED`) |
| scorm-player | `http://scorm.local:8091` | Launch-страница, runtime API (`/api/v1/runtime/*`) |
| scorm-content | `http://scorm.local:8092` | Статическая раздача файлов пакетов и SCORM API для контента |

Цепочка запуска:

1. main-app создаёт одноразовый launch-токен (5 минут) и встраивает плеер в `iframe`.
2. Плеер погашает launch-токен, выпускает runtime-токен и держит его только в памяти страницы.
3. Плеер встраивает страницу-хост с origin контента по подписанному URL (`/c/<package>/<expires>/<base64url(origin клиента)>/<hmac>/…`). Launch-страницу может встраивать только origin клиента, контент - только плеер и origin клиента (`frame-ancestors`).
4. Хост публикует `window.API` / `window.API_1484_11` и загружает SCO. API общается с плеером через `postMessage`, плеер — с сервером по runtime-токену.

Файлы пакетов лежат в S3-хранилище Selectel (бакет `dsf-private`). Браузер получает файлы курса только через сервер контента, напрямую из S3 они не отдаются. Сервер контента читает их из S3 и отдаёт с поддержкой `Range`; он отдаёт только файлы разрешённых типов, ничего не исполняет и ставит CSP, `frame-ancestors`, `nosniff`, `Referrer-Policy`.

## Запуск

```bash
cp .env.example .env
docker compose -p scorm up -d
docker compose -p scorm exec -w /app main-app php yii migrate --interactive=0
```

В `hosts` должны быть записи:

```text
127.0.0.1 app.local
127.0.0.1 scorm.local
```

Без Docker: PHP 8.1+ с `pdo_mysql`, `zip`, `intl`; MySQL 5.7+ / MariaDB 10.3+; `composer install`, `php yii migrate`, затем `./start-servers.sh` или `start-servers.bat`.

## Конфигурация

Все параметры задаются переменными окружения (`.env`).

| Переменная | По умолчанию | Назначение |
| --- | --- | --- |
| `YII_DEBUG` | `0` | Режим отладки. В production должен быть выключен |
| `COOKIE_VALIDATION_KEY` | — | Ключ подписи cookie main-app. Обязателен при `YII_DEBUG=0` |
| `CONTENT_SIGNING_KEY` | — | Ключ подписи URL контента. Обязателен при `YII_DEBUG=0` |
| `MAIN_APP_HOST` / `MAIN_APP_PORT` | `app.local` / `8090` | Адрес main-app |
| `SCORM_PLAYER_HOST` / `SCORM_PLAYER_PORT` | `scorm.local` / `8091` | Адрес плеера |
| `SCORM_CONTENT_HOST` / `SCORM_CONTENT_PORT` | `scorm.local` / `8092` | Адрес сервера контента |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `127.0.0.1`, `3306`, `scorm_lms`, `root`, `12345` | Подключение к MySQL |
| `DEMO_UI_ENABLED` | `0` | Демо-интерфейс main-app без аутентификации. В production должен быть выключен |
| `LAUNCH_TOKEN_TTL` | `300` | Время жизни launch-токена, сек |
| `RUNTIME_TOKEN_TTL` | `7200` | Время жизни runtime-токена без активности, сек |
| `S3_ACCESS_KEY` / `S3_SECRET_KEY` | — | Ключи Selectel S3 (те же, что у DNM). Без них работа с пакетами невозможна |
| `S3_CONTAINER` | `dsf-private` | Бакет, в котором хранятся файлы пакетов (префикс `Y/m/d/scorm-<id>/`) |
| `S3_REGION` | `ru-1` | Регион Selectel S3 |

`common/config/main-local.php` подключается поверх общего конфига для локальных переопределений.

## Runtime

- Модель данных SCORM 1.2 и 2004 описана в `common/services/ScormDataModel.php` и используется и сервером, и клиентским API.
- Клиентский API (`scorm-player/content/scorm-api.js`) реализует состояния и коды ошибок обеих спецификаций, отправляет на сервер только изменённые значения.
- Initialize, Commit и Terminate выполняются на сервере в транзакции; невалидные значения отклоняются. В режиме `review` сервер ничего не записывает.
- Попытка: продолжение после `suspend`, завершённая попытка открывается в режиме просмотра, новая - по кнопке Start new attempt.
- Для SCORM 2004 цели из `imsss:objectives` предзаполняются в `cmi.objectives`, `minNormalizedMeasure` передаётся как `cmi.scaled_passing_score`.
- Sequencing and Navigation SCORM 2004 не реализован: модули открываются в любом порядке.

## Интеграция с DNM

Клиент DNM создаётся консольной командой, ключ и секрет выводятся один раз и хранятся в конфиге DNM:

```bash
php yii client/create "DNM" https://dnm.example.com https://dnm.example.com/training/scorm-callback/progress
```

- API-ключ - заголовок `Authorization: Bearer <ключ>` для API main-app; в БД хранится только хэш.
- Origin DNM - `frame-ancestors` launch-страницы и адресат `postMessage`.
- Секрет подписи - HMAC обратных вызовов.
- Параметры `.env`: адреса `MAIN_APP_*`, `SCORM_PLAYER_*`, `SCORM_CONTENT_*` должны быть доступны DNM и браузеру; `CONTENT_SIGNING_KEY` обязателен при `YII_DEBUG=0`; лимиты пакетов - `MAX_PACKAGE_SIZE`, `MAX_EXTRACTED_SIZE`, `MAX_PACKAGE_FILES`.

Обратные вызовы:

- После Initialize, Commit и Terminate (кроме `review` и `preview`) в той же транзакции в очередь ставится джоба `SendCallbackJob` со снимком прохождения. `version` растёт на каждый снимок сквозь попытки одного ключа прохождения.
- Просроченные launch/runtime-токены удаляет `php yii callback/cleanup`;.

## Тесты

```bash
docker compose -p scorm exec -w /app main-app vendor/bin/phpunit
```

Набор `unit` проверяет распаковку, парсер манифеста, модель данных и подпись URL. Набор `http` проверяет заголовки безопасности и доступ к API на запущенном окружении и пропускается, если оно недоступно.

## API

| Метод | URL | Описание |
| --- | --- | --- |
| GET | `/api/v1/scorm-packages` | Список пакетов |
| GET | `/api/v1/scorm-packages/{id}` | Пакет и его SCO |
| POST | `/api/v1/scorm-packages` | Загрузка пакета по URL (`source_url`, `file_name`), только один SCO |
| DELETE | `/api/v1/scorm-packages/{id}` | Удаление пакета |
| POST | `/api/v1/scorm-launch/create-token` | Ссылка запуска (`package_id`, `mode`: `normal`/`review`/`preview`, `registration_key`, `learner_id`, `learner_name`, `course_ref`); ответ `launch_url`, `mode` |
| GET | `/api/v1/scorm-results/attempt/{id}` | Результаты попытки |

## Ограничения

- API main-app требует `Authorization: Bearer <ключ>` клиента; каждый клиент видит только свои пакеты и попытки.
- Все пакеты делят один origin контента, поэтому пакеты могут читать `localStorage` друг друга. Полная изоляция требует отдельного поддомена на пакет.
- Встроенный сервер PHP подходит только для разработки.
