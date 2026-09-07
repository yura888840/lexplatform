# Архитектура LexPlatform

## Принципы (ТЗ §9.2)

**DDD**: домен изолирован от фреймворка. Сущности содержат бизнес-инварианты:
- `Answer` можно создать только от пользователя с ролью `lawyer` — проверка в конструкторе;
- `Question::acceptAnswer()` разрешён только автору вопроса;
- `Review::approve()` атомарно пересчитывает рейтинг юриста.

**CQRS**: изменения состояния идут через Command + Handler (`Application/`), чтение — напрямую через QueryBuilder в контроллерах (для MVP допустимо; при росте выделяются Query-хендлеры и read-модели на Redis/реплике).

**Event-Driven**: доменные события (`UserRegistered`, `QuestionPosted`) реализуют `AsyncMessageInterface` и маршрутизируются Symfony Messenger в RabbitMQ. Хендлеры в `Infrastructure/Messaging/`. Retry ×3 с экспоненциальной задержкой, dead-letter в `failed` transport (Doctrine).

## Bounded Contexts (MVP)

| Контекст | Сущности | Статус |
|---|---|---|
| Identity | User, RefreshToken | ✅ MVP |
| Lawyer | LawyerProfile, Specialization, Review | ✅ MVP |
| Consultation | Question, Answer | ✅ MVP |
| Content | Article, Category | ✅ MVP |
| LegalBase | LawDocument, CourtDecision | 🔜 V2 |
| Marketplace | Tender, VideoCall, Escrow | 🔜 V2 |
| Payment / Subscription | Payment, Subscription, Plan | 🔜 V2 |
| AI | RAGQuery, DocumentDraft | 🔜 V3 |

Новый контекст добавляется по шаблону: `Domain/<Ctx>/{Entity,Repository}` + mapping в `config/packages/doctrine.yaml` + миграция.

## Аутентификация

- Access JWT — 15 минут (lexik/jwt, RS256, ключи генерируются `make jwt-keys`).
- Refresh — 30 дней, хранится в БД **SHA-256-хешем**, ротация при каждом refresh (компрометация БД не даёт живых токенов).
- Роли через `role_hierarchy` Symfony Security; superadmin > admin > moderator/editor.

## Поиск

Три индекса OpenSearch: `lex_lawyers`, `lex_questions`, `lex_articles`. Индексация:
1. асинхронно по событиям (QuestionPosted → QuestionPostedHandler);
2. полная — `bin/console app:search-reindex` (после деплоя/сидинга).

Запрос — `multi_match` с бустами (`title^3`) и `fuzziness: AUTO` (SRCH-04). В V2 добавляются агрегации-фасеты и suggest.

## Frontend

Next.js 14 App Router. Публичные страницы (каталог, Q&A, статьи) — SSR (`force-dynamic`) для SEO и Core Web Vitals; SSR-запросы идут во внутренний URL nginx, браузерные — в публичный. Формы (login/register/ask) — клиентские компоненты, токены в localStorage (для V2 рекомендуется переход на httpOnly cookies + BFF).

## Известные упрощения MVP (осознанный технический долг)

- Чтение в контроллерах без Query-bus — выделить при появлении read-моделей.
- Email-верификация: событие и статус `pending_verification` есть, endpoint подтверждения — заглушка под V1.1.
- Rate limiting настроен в framework.yaml, привязка к firewall — V1.1.
- OAuth Google (AUTH-02) — точка расширения в AuthController (`/auth/oauth/{provider}`).
