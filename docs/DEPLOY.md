# Деплой LexPlatform

## Один сервер (MVP, до ~10K пользователей — ТЗ §13.2)

Требования: Ubuntu 22.04+, 4 vCPU / 8 GB RAM / 100 GB SSD, Docker + Compose v2.

```bash
git clone <repo> /opt/lexplatform && cd /opt/lexplatform
cp .env.example .env        # обязательно смените ВСЕ пароли и JWT_PASSPHRASE
make init
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Обновление: `./scripts/deploy.sh` (pull → build → up → миграции → health-check).

## TLS и CDN

Терминируйте TLS на Cloudflare (proxied DNS) или поставьте перед nginx caddy/traefik с Let's Encrypt. Cloudflare также закрывает DDoS и кеширует статику (ТЗ §9.1).

## Бекапы

```bash
# PostgreSQL — ежедневно, хранить 30 дней
docker compose exec -T postgres pg_dump -U lex lexplatform | gzip > backup_$(date +%F).sql.gz
# MinIO — mc mirror на внешний S3
```

Добавьте в cron + выгрузку в offsite-хранилище. Восстановление отрабатывайте раз в квартал.

## Мониторинг

- `GET /api/health` — liveness для балансировщика/UptimeRobot.
- Sentry: задать `SENTRY_DSN`, подключить sentry/sentry-symfony (V1.1).
- Prometheus + Grafana: node-exporter, postgres-exporter, rabbitmq — по мере роста.

## Масштабирование (100K+, ТЗ §13.2–13.3)

1. Вынести PostgreSQL/OpenSearch/Redis в managed-сервисы или отдельные ноды.
2. PgBouncer + read-реплика для Query-стороны CQRS.
3. Приложение и воркеры → Kubernetes (образы уже stateless, конфиг через env).
4. Далее — микросервисная декомпозиция по bounded contexts (ТЗ §13.3): контексты уже изолированы, выделение сервиса = вынос каталога Domain/<Ctx> + свой деплой.

## Чек-лист безопасности перед продом

- [ ] Все пароли из .env.example заменены
- [ ] JWT-ключи сгенерированы на сервере, private.pem вне git (уже в .gitignore)
- [ ] adminer/mailhog выключены (prod overlay это делает)
- [ ] Порты 5433/9200/15672/9001 закрыты фаерволом (наружу только 80/443)
- [ ] Бекапы настроены и проверено восстановление
- [ ] CORS_ALLOW_ORIGIN сужен до боевого домена
