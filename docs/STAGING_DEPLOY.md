# Деплой LexPlatform на VPS (staging)

Пошагова інструкція для тестового розгортання на одному VPS. Усе піднімається через Docker Compose, HTTPS видає Caddy автоматично (Let's Encrypt). Час: ~20-30 хвилин.

---

## 0. Що знадобиться

- **VPS**: Ubuntu 22.04 / 24.04, мінімум **4 vCPU / 8 GB RAM / 40 GB SSD**.
  OpenSearch + PostgreSQL + RabbitMQ разом їдять пам'ять — на 4 GB буде падати.
- **Домен** (напр. `staging.lexplatform.ua`) з можливістю додати A-запис.
- SSH-доступ до сервера з правами sudo.

---

## 1. Налаштувати DNS

У панелі свого домену додайте A-запис, що вказує на IP вашого VPS:

```
Тип   Ім'я       Значення (IP VPS)     TTL
A     staging    203.0.113.10          300
```

Перевірте, що записалось (з локального комп'ютера):

```bash
dig +short staging.lexplatform.ua
# має повернути IP вашого VPS
```

> Без коректного DNS Caddy не зможе отримати TLS-сертифікат. Дочекайтесь, поки `dig` покаже правильний IP, перш ніж рухатись далі.

---

## 2. Підключитись до сервера й підготувати систему

```bash
ssh root@203.0.113.10

# оновлення системи
apt update && apt upgrade -y

# базові утиліти
apt install -y git ufw curl
```

### Фаєрвол

```bash
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable
ufw status
```

> Порти БД, OpenSearch, RabbitMQ, MinIO **не відкриваємо** назовні — доступ до них тільки всередині Docker-мережі. Назовні дивляться лише 80/443 (Caddy) і 22 (SSH).

---

## 3. Встановити Docker

```bash
curl -fsSL https://get.docker.com | sh
docker --version
docker compose version   # має бути v2.x
```

(Необов'язково) створити окремого користувача замість root:

```bash
adduser deploy
usermod -aG docker,sudo deploy
# далі працюйте під deploy: su - deploy
```

---

## 4. Отримати код

Якщо код у Git-репозиторії:

```bash
cd /opt
git clone <URL_вашого_репозиторію> lexplatform
cd lexplatform
```

Якщо коду немає в Git — залийте архів `lexplatform.zip` через scp з локальної машини:

```bash
# з ЛОКАЛЬНОГО комп'ютера
scp lexplatform.zip root@203.0.113.10:/opt/
# назад на сервері:
cd /opt && apt install -y unzip && unzip lexplatform.zip && cd lexplatform
```

---

## 5. Налаштувати змінні оточення

```bash
cp .env.staging.example .env
nano .env
```

Обов'язково замініть:

| Змінна | Що вписати |
|---|---|
| `STAGING_DOMAIN` | ваш домен, напр. `staging.lexplatform.ua` (без `https://`) |
| `APP_PUBLIC_URL` | `https://staging.lexplatform.ua` |
| `POSTGRES_PASSWORD` | згенерувати: `openssl rand -hex 24` |
| `RABBITMQ_PASSWORD` | згенерувати окремо |
| `MINIO_ROOT_PASSWORD` | згенерувати окремо |
| `JWT_PASSPHRASE` | згенерувати окремо |
| `LIQPAY_PUBLIC_KEY` / `LIQPAY_PRIVATE_KEY` | **sandbox**-ключі з кабінету liqpay.ua |

Швидко нагенерувати паролі:

```bash
for i in 1 2 3 4; do openssl rand -hex 24; done
```

> Для staging беріть саме **sandbox**-ключі LiqPay — реальних грошей не спишеться, але весь платіжний флоу (checkout → webhook → payout) працюватиме.

---

## 6. Зібрати й запустити

Staging використовує два compose-файли: базовий + оверлей `docker-compose.staging.yml` (prod-образи з запеченим кодом + Caddy для HTTPS).

> ⚠️ **Завжди вказуйте обидва `-f`.** Якщо запустити просто `docker compose up` без прапорців, Docker автоматично підхопить `docker-compose.override.yml` (dev-режим із монтуванням коду та `npm run dev`) — і frontend впаде з помилкою `Cannot find module '/app/server.js'`, бо dev-режим не збирає standalone. На staging файл override не потрібен.

```bash
# збірка образів (перший раз ~5-8 хв)
docker compose -f docker-compose.yml -f docker-compose.staging.yml build

# запуск інфраструктури (БД, кеш, черга, пошук, сховище)
docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d \
  postgres redis rabbitmq opensearch minio

# зачекати, поки postgres підніметься
sleep 15

# запустити застосунок і Caddy
docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d
```

Щоб не писати довгі команди — задайте псевдонім на час сесії:

```bash
alias dc='docker compose -f docker-compose.yml -f docker-compose.staging.yml'
dc ps        # тепер коротко
```

---

## 7. Ініціалізувати базу даних

Ці кроки виконуються **один раз** при першому розгортанні:

```bash
alias dc='docker compose -f docker-compose.yml -f docker-compose.staging.yml'

# міграції (створює всі таблиці V1+V2+V3)
dc exec -T app php bin/console doctrine:migrations:migrate --no-interaction

# демо-дані: юристи, категорії, питання, статті
dc exec -T app php bin/console app:seed-demo

# правова база: тарифи PRO + демо-НПА + судові рішення
dc exec -T app php bin/console app:seed-legal-base

# 19 галузей права + тариф «Юрист»
dc exec -T app php bin/console app:seed-practice-areas

# індексація в OpenSearch
dc exec -T app php bin/console app:search-reindex
```

> JWT-ключі генеруються автоматично при старті контейнера (entrypoint), окремо робити нічого не треба.

---

## 8. Перевірити

Дайте Caddy ~30-60 секунд на отримання сертифіката, потім:

```bash
# health-check API (з сервера)
curl -fsS https://staging.lexplatform.ua/api/health
# очікувано: {"status":"ok","db":true,...}
```

У браузері відкрийте:

- `https://staging.lexplatform.ua` — головна
- `https://staging.lexplatform.ua/lawyers` — каталог юристів
- `https://staging.lexplatform.ua/cabinet/profile` — дашборд керування профілем
- `https://staging.lexplatform.ua/laws` — база законодавства

**Демо-акаунти** (створені сідингом):

```
admin@lexplatform.local            / admin12345
client@lexplatform.local           / client12345
ihor.shevchenko@lexplatform.local  / lawyer12345
```

---

## 9. Логи й діагностика

```bash
alias dc='docker compose -f docker-compose.yml -f docker-compose.staging.yml'

dc ps                      # статус усіх контейнерів
dc logs -f app worker      # логи бекенду і воркера
dc logs -f caddy           # логи Caddy (тут видно проблеми з TLS)
dc logs -f node            # логи Next.js
```

### Типові проблеми

| Симптом | Причина / рішення |
|---|---|
| Caddy не видає сертифікат | DNS ще не оновився або порт 80 закритий фаєрволом. Перевірте `dig` і `ufw status`. Дивіться `dc logs caddy` |
| `502 Bad Gateway` на `/` | frontend ще збирається/стартує. `dc logs node`, зачекайте 1-2 хв |
| `502` на `/api/*` | app не піднявся або впала міграція. `dc logs app` |
| `Cannot find module '/app/server.js'` | Запустили без `-f` прапорців → підхопився dev-override. Зупиніть (`docker compose down`) і запускайте з обома `-f ... -f docker-compose.staging.yml` |
| `Fetch API cannot load http://localhost:8080...` у браузері | Старий білд frontend з хардкодом localhost. Оновіть код і **пересоберіть frontend начисто** (див. нижче). Браузер тепер ходить на відносний `/api/v1` того самого домену |
| `503` на завантаженні аватара/документа | MinIO або бакет недоступні. Перевірте `dc ps minio` і `dc logs minio-init` (має створити бакет). Медіа віддається через Caddy `/media` |
| OpenSearch падає / OOM | мало RAM. Потрібно ≥ 8 GB, або зменшіть heap у `docker-compose.yml` (`OPENSEARCH_JAVA_OPTS`) |
| `db:false` у health | postgres не готовий. `dc logs postgres`, повторіть міграцію |

---

## 10. Оновлення коду (наступні деплої)

```bash
cd /opt/lexplatform
alias dc='docker compose -f docker-compose.yml -f docker-compose.staging.yml'

git pull                                  # або залийте новий архів
dc build
dc up -d
dc exec -T app php bin/console doctrine:migrations:migrate --no-interaction
dc exec -T app php bin/console app:search-reindex || true
curl -fsS https://staging.lexplatform.ua/api/health
```

---

## 11. Зупинка / очищення

```bash
alias dc='docker compose -f docker-compose.yml -f docker-compose.staging.yml'

dc stop            # зупинити (дані зберігаються)
dc down            # зупинити й видалити контейнери (volume лишаються)
dc down -v         # ПОВНЕ очищення разом з даними БД — обережно!
```

---

## Примітки щодо безпеки staging

- Це **тестове** середовище: демо-паролі, sandbox-платежі, без реальних персональних даних.
- LiqPay webhook (`/api/v1/payments/webhook/liqpay`) має бути доступний з інтернету — Caddy це забезпечує, IP-фільтр не ставте (захист — підпис + звірка суми).
- Для повноцінного прод-розгортання (керовані БД, K8s, ExternalSecrets, бекапи) дивіться `docs/DEPLOY.md` і `docs/KUBERNETES.md`.
- Пошта: за замовчуванням `MAILER_DSN=null://null` — листи мовчки відкидаються, лог воркера чистий. Реєстрація й оплата працюють без пошти. Для реальних листів пропишіть `MAILER_DSN` (напр. SendGrid) у `.env`.
