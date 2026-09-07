# Content Policy & Monetization (V3)

Реализация бизнес-требований «статьи или деньги», верификация, комиссия 30%.

## Правило «статьи или деньги»

Каждый юрист выбирает режим участия (`lawyer_profiles.contribution_mode`):

| Режим | Условие соответствия |
|---|---|
| `articles` (по умолчанию) | Статья в каждую выбранную отрасль права + свежая статья не старше 3 дней (цель «через день»). Новичкам — 14 дней на первую статью |
| `paid` | Активная подписка «Тариф „Юрист“» (990 грн/мес, slug `lawyer-monthly`) |

Compliance-статусы: `onboarding → ok → warning (3–7 дн. без статьи) → non_compliant (>7 дн. или нет подписки)`.
Санкция non_compliant: снятие featured + понижение в выдаче каталога (compliance_penalty в ORDER BY). Профиль не скрывается.
Пересчёт: онлайн при действиях юриста + ежедневный cron `app:compliance-recheck` (K8s CronJob 03:15).

Логика изолирована в `Domain/Lawyer/Service/ComplianceChecker` — чистый класс, покрыт юнит-тестами. Пороги — константы класса.

## Статьи юристов

Флоу: юрист сабмитит (`POST /api/v1/articles`) → статус `review` → редактор публикует (`POST /api/v1/editor/articles/{id}/publish`, ROLE_EDITOR) → событие `ArticlePublished` → счётчики юриста + индексация.

Доменные инварианты в `SubmitLawyerArticleHandler`:
1. Автор — роль lawyer с профилем.
2. Отрасль статьи ∈ специализаций юриста («в ті галузі писали б статті»).
3. Объём ≥ `app.min_article_chars` = 3600 знаков чистого текста (2 стр. × ~1800 зн.).
4. Обязательный чекбокс передачи прав → запись `user_consents` (type=copyright_transfer, версия условий, IP, timestamp) + флаг `articles.copyright_transferred`. Это юридическая фиксация перехода прав порталу.

## Рейтинг за статьи

`content_score = min(0.5, количество_статей × 0.05)`. Эффективный рейтинг = отзывы + content_score (кап 5.0). Каталог сортируется по нему; отображается пользователям как единый рейтинг. Сортировка `?sort=articles` — по числу статей.

## Отрасли права

19 отраслей из бизнес-списка (advok.in.ua), slug'и совместимы с их URL. Сидинг: `app:seed-practice-areas` (идемпотентно, входит в `make init`). Юрист выбирает отрасли в кабинете (`PATCH /api/v1/lawyers/me`, минимум одна).

## Верификация

`POST /api/v1/lawyers/me/documents` (multipart): `advocate_certificate` (обязателен) / `education_certificate` (желателен). PDF/JPEG/PNG ≤ 10 МБ, хранение приватно в MinIO/S3 (`S3Storage`), админ смотрит через presigned URL (15 мин). Одобрение свідоцтва адвоката ⇒ `is_verified = true` (доменная логика в `VerificationDocument::approve()`).

## Согласия (GDPR)

`user_consents`: `personal_data` — обязательна при регистрации юриста (публичный каталог = распространение персданных), `copyright_transfer` — при каждом сабмите статьи. Хранится версия текста согласия, дата, IP — доказательная база.

## Комиссия 30%

Клиент оплачивает консультацию: `POST /api/v1/payments/checkout {type: "consultation", lawyer_slug, hours}` → сумма = hourly_rate × hours → LiqPay. При success-webhook `PaymentSucceededHandler` создаёт `Payout`: gross / комиссия 30% (`app.commission_rate`) / net 70% юристу. Идемпотентно: payout уникален по payment_id (UNIQUE-констрейнт). Юрист видит выплаты: `GET /api/v1/payouts/me`. Фактическое перечисление (p2p-split LiqPay или ручной реестр) — следующая итерация; сейчас payout в статусе `pending`.

## Известные упрощения

- Выплата юристу — учётная запись pending, без автоматического сплита (LiqPay p2p-split — V3.1).
- Снятие санкций мгновенно при публикации статьи, повторная выдача featured — вручную/оплатой.
- Antiplagiat/качество статей — на редакторе; автоматическая проверка уникальности — V3.1.
