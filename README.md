# LexPlatform

Юридический онлайн-маркетплейс нового поколения. Монорепозиторий MVP (V1) по Техническому Заданию v1.0.

**Стек:** Symfony 7 / PHP 8.4 · Next.js 14 / React 18 / TypeScript · PostgreSQL 16 · OpenSearch 2 · Redis 7 · RabbitMQ 3.13 · MinIO · Docker

## Быстрый старт

Требования: Docker + Docker Compose v2, make.

```bash
cp .env.example .env
make init
```

`make init` делает всё сам: сборка образов, `composer install`, генерация JWT-ключей, миграции, демо-данные, индексация OpenSearch.

После запуска:

| Сервис | URL |
|---|---|
| Frontend (Next.js) | http://localhost:3000 |
| API | http://localhost:8080/api/v1 |
| Health check | http://localhost:8080/api/health |
| Adminer (БД) | http://localhost:8081 |
| MailHog (письма) | http://localhost:8025 |
| RabbitMQ UI | http://localhost:15672 |
| MinIO Console | http://localhost:9001 |
| OpenSearch | http://localhost:9200 |

**Демо-аккаунты** (после `make db-fixtures`):

```
admin@lexplatform.local  / admin12345
client@lexplatform.local / client12345
ihor.shevchenko@lexplatform.local / lawyer12345
```

## Команды

```bash
make help          # список всех команд
make up / down     # запуск / остановка
make logs          # логи app + worker
make test          # PHPUnit
make lint          # PHPStan + ESLint
make db-migrate    # миграции
make db-fixtures   # демо-данные
make search-index  # переиндексация OpenSearch
```

## Что реализовано (MVP scope, ТЗ §14.1)

- **Auth**: регистрация email+password, JWT (access 15 мин + refresh 30 дней с ротацией), роли client/lawyer/admin, soft-delete (GDPR). Точка расширения для OAuth Google.
- **Каталог юристов**: фильтры (специализация, город, рейтинг, цена, онлайн), сортировки, featured-продвижение, верификация, публичный профиль по slug.
- **Q&A**: публичные вопросы по 12 правовым категориям, ответы юристов (доменное ограничение — только роль lawyer), выбор лучшего ответа автором, анонимность, счётчики просмотров.
- **Отзывы**: 1–5 звёзд, модерация pending→approved, автоматический пересчёт рейтинга юриста.
- **CMS**: статьи/новости/блог, workflow draft→published, SEO-поля, счётчики.
- **Поиск**: OpenSearch (multi_match, fuzziness, три индекса), полная переиндексация командой.
- **Event-Driven**: доменные события через Symfony Messenger + RabbitMQ (welcome email при регистрации, индексация при публикации вопроса), retry, failed transport.
- **Инфраструктура**: Docker Compose (dev/prod overlay), Nginx, воркер очередей, health-check, GitHub Actions CI (lint, PHPStan, тесты, сборка).

## Архитектура

DDD + CQRS (ТЗ §9.2):

```
backend/src/
├── Domain/          # чистая доменная логика: Identity, Lawyer, Consultation, Content
├── Application/     # use-cases: Command + Handler (RegisterUser, CreateQuestion)
├── Infrastructure/  # OpenSearch, Messenger-хендлеры, персистентность
└── UI/              # Api-контроллеры, Console-команды
```

Подробности: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) · Деплой: [docs/DEPLOY.md](docs/DEPLOY.md)

## Деплой в production

```bash
./scripts/deploy.sh
# или вручную:
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Быстрый тестовый деплой на VPS с авто-HTTPS: см. [docs/STAGING_DEPLOY.md](docs/STAGING_DEPLOY.md).

TLS терминируется на Cloudflare/балансировщике. Секреты — только через `.env` на сервере (не в git). Масштабирование до 100K+ пользователей — миграция на Kubernetes, см. docs/DEPLOY.md.

## Модули V2 (реализовано)

- **Платежи LiqPay**: checkout (подписки PRO + продвижение featured), webhook с timing-safe проверкой подписи и сверкой суммы, идемпотентная обработка ретраев, история платежей, активация/продление подписки через событие `PaymentSucceeded`. Sandbox-режим поддерживается из коробки (`sandbox_*` ключи).
- **База законодательства**: НПА с версионированием редакций (LEG-02), суды и судебные решения, полнотекстовый поиск Postgres FTS (`websearch_to_tsquery` + GIN, tsvector-триггеры), PRO-гейт на полный текст, импорт из JSON (`app:import-laws`, батчами, идемпотентно по slug), сидинг демо-базы (`app:seed-legal-base`).
- **Kubernetes**: Helm-чарт в `deploy/helm/lexplatform` — backend (fpm+nginx sidecar), worker, frontend, Job миграций как helm-хук, Ingress, HPA, PDB, Bitnami-subcharts c переключением на managed-сервисы. Прод-образы: `docker/php-prod`, `docker/node-prod`, публикация в ghcr по git-тегу. Подробно: [docs/KUBERNETES.md](docs/KUBERNETES.md).

Новые страницы фронта: `/laws` (поиск по базе), `/laws/{slug}` (документ + версии + PRO-пейволл), `/pro` (тарифы, auto-submit форма LiqPay), `/payments/result` (поллинг статуса).

## Модуль V3 — Content Policy & Monetization (реализовано)

Бизнес-правило «або статті, або гроші»: юрист пишет статьи в свои отрасли права (мин. 2 страницы = 3600 знаков, цель — через день, права переходят порталу с юридической фиксацией согласия) либо оплачивает тариф «Юрист» 990 грн/мес. Compliance-движок (onboarding/ok/warning/non_compliant) с ежедневным cron-пересчётом и санкцией понижения в каталоге. Рейтинг растёт от количества статей (+0.05/статья, кап +0.5). 19 отраслей права по бизнес-списку. Загрузка свідоцтва адвоката/про освіту в MinIO с модерацией и presigned-доступом. Комиссия площадки 30% с оплат консультаций → Payout юристу 70% (идемпотентно). Кабинет юриста на фронте: compliance-дашборд, переключение режима, оплата LiqPay, загрузка документов, форма статьи с прогресс-баром объёма. Подробно: [docs/CONTENT_POLICY.md](docs/CONTENT_POLICY.md).

## Адмін-панель (`/admin`)

Панель для персоналу платформи з рольовим доступом. **Адміністратор** (ROLE_ADMIN): дашборд метрик (користувачі, черга модерації, контент, дохід/комісія), керування користувачами (пошук, бан/розбан, зміна ролі — призначення admin лише суперадміном), модерація відгуків, верифікація документів. **Модератор** (ROLE_MODERATOR): лише модерація відгуків і верифікація документів (дашборд і користувачі приховані). Бекенд: `AdminController` (`/api/v1/admin/*`) з розділенням доступу в security.yaml — `/reviews`, `/documents`, `/lawyers` під ROLE_MODERATOR, решта під ROLE_ADMIN. Верифікація документів показує їх через presigned URL (15 хв). Схвалення свідоцтва адвоката автоматично верифікує профіль юриста; схвалення відгуку перераховує рейтинг. Захист від self-модифікації (не можна забанити себе чи іншого адміна). Посилання «Адмін» у шапці для персоналу.

## Кабінет клієнта (`/account`)

Особистий кабінет для клієнтів (role: client) із трьома вкладками: **Профіль** (редагування імені, телефону, мови, завантаження аватара), **Мої питання** (список поставлених питань зі статусами, кількістю відповідей і позначкою прийнятої відповіді), **Платежі** (історія оплачених консультацій і підписок). Бекенд: `AccountController` (`/api/v1/account`, `/questions`, `/payments`, `/avatar`) — доступний будь-якому автентифікованому користувачу. Клієнт потрапляє в кабінет через своє ім'я в шапці. Порожні стани, валідація, аватар у MinIO.

## Дашборд керування профілем юриста (`/cabinet/profile`)

Преміальна SaaS-сторінка (стиль Stripe / Linear / Notion) для редагування власного профілю юристом. 17 секцій у sticky-навігації з індикаторами заповненості: Профіль, Контакти, Юридична інформація, Офіси, Галузі права, Послуги та ціни, Публікації, Судові справи, Документи, Відгуки, Статистика, Підписка, SEO, Сповіщення, Приватність. Особливості: липка кнопка «Зберегти», індикатор автозбереження, прогрес-бар заповненості (обчислюється з даних), драг-н-дроп завантаження, сучасні перемикачі та segmented-контроли, преміальні таблиці послуг/публікацій/справ, аналітичний дашборд статистики (KPI-віджети, стовпчикова діаграма, donut-графіки, прогрес-бари), керування відгуками з відповідями/приховуванням/скаргами, порожні стани, валідація, тости успіху. React + TailwindCSS, повністю адаптивний, українською. Файл: `frontend/src/app/cabinet/profile/LawyerProfileManager.jsx`.

## Roadmap

- **V2 (осталось)**: видеозвонки (LiveKit), тендеры, приватные консультации с эскроу.
- **V3**: AI-ассистент (RAG по НПА), генерация документов, классификация вопросов, mobile.
- **Enterprise**: микросервисы, K8s, White Label, SLA 99.9%.

Каркас под V2/V3 заложен: bounded contexts изолированы, события асинхронные, поисковый слой отделён.
