# SCORM LMS

Минимальная LMS на Yii2 с поддержкой SCORM 1.2 и SCORM 2004: загрузка пакетов, запуск курсов в изолированном плеере, сохранение прогресса.

## Архитектура

Приложение работает на трёх origin. Недоверенный контент курсов никогда не выполняется на origin, где есть данные или API.

| Сервис | Адрес по умолчанию | Назначение |
| --- | --- | --- |
| main-app | `http://app.local:8090` | Каталог пакетов, загрузка, результаты, страница курса |
| scorm-player | `http://scorm.local:8091` | Launch-страница, runtime API (`/api/v1/runtime/*`) |
| scorm-content | `http://scorm.local:8092` | Статическая раздача файлов пакетов и SCORM API для контента |

Цепочка запуска:

1. main-app создаёт одноразовый launch-токен (5 минут) и встраивает плеер в `iframe`.
2. Плеер погашает launch-токен, выпускает runtime-токен и держит его только в памяти страницы.
3. Плеер встраивает страницу-хост с origin контента по подписанному URL (`/c/<package>/<expires>/<hmac>/…`).
4. Хост публикует `window.API` / `window.API_1484_11` и загружает SCO. API общается с плеером через `postMessage`, плеер — с сервером по runtime-токену.

Файлы пакетов лежат в `storage/scorm-content` вне веб-корня. Сервер контента отдаёт только файлы разрешённых типов, ничего не исполняет и ставит CSP, `frame-ancestors`, `nosniff`, `Referrer-Policy`.

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
| `LAUNCH_TOKEN_TTL` | `300` | Время жизни launch-токена, сек |
| `RUNTIME_TOKEN_TTL` | `7200` | Время жизни runtime-токена без активности, сек |

`common/config/main-local.php` подключается поверх общего конфига для локальных переопределений.

## Runtime

- Модель данных SCORM 1.2 и 2004 описана в `common/services/ScormDataModel.php` и используется и сервером, и клиентским API.
- Клиентский API (`scorm-player/content/scorm-api.js`) реализует состояния и коды ошибок обеих спецификаций, отправляет на сервер только изменённые значения.
- Commit и Terminate выполняются на сервере в транзакции; невалидные значения отклоняются.
- Попытка: продолжение после `suspend`, завершённая попытка открывается в режиме просмотра, новая — по кнопке «Start new attempt».
- Для SCORM 2004 цели из `imsss:objectives` предзаполняются в `cmi.objectives`, `minNormalizedMeasure` передаётся как `cmi.scaled_passing_score`.
- Sequencing and Navigation SCORM 2004 не реализован: модули открываются в любом порядке.

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
| POST | `/api/v1/scorm-packages/upload` | Загрузка пакета (поле `file`) |
| DELETE | `/api/v1/scorm-packages/{id}` | Удаление пакета |
| POST | `/api/v1/scorm-launch/create-token` | Ссылка запуска (`package_id` или `sco_id`, `new_attempt`) |
| GET | `/api/v1/scorm-results/attempt/{id}` | Результаты попытки |

## Ограничения

- Нет аутентификации: все действия выполняются от имени учащегося `learnerId` из `common/config/params.php`. API main-app открыт.
- Все пакеты делят один origin контента, поэтому пакеты могут читать `localStorage` друг друга. Полная изоляция требует отдельного поддомена на пакет.
- Встроенный сервер PHP подходит только для разработки.
