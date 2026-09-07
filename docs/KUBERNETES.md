# Деплой LexPlatform в Kubernetes (Helm)

Чарт: `deploy/helm/lexplatform`. Профиль по ТЗ §13.2 для 100K+ пользователей.

## Состав релиза

| Ресурс | Что делает |
|---|---|
| Deployment backend | php-fpm + nginx sidecar в одном поде (fastcgi по localhost) |
| Deployment worker | `messenger:consume async`, graceful shutdown 60с, авто-рестарт по time/memory-limit |
| Deployment frontend | Next.js standalone (SSR ходит в backend-Service, браузер — через Ingress) |
| Job migrate | helm-хук post-install/post-upgrade: миграции → сидинг → реиндекс |
| Ingress | `/api` → backend, `/` → frontend; TLS через cert-manager |
| HPA ×2 | backend 2→10 подов, frontend 2→8 по CPU 70% |
| PDB ×2 | minAvailable: 1 — переживаем node drain |
| Subcharts | Bitnami PostgreSQL/Redis/RabbitMQ + OpenSearch (отключаемые) |

## Staging (всё в кластере)

```bash
cd deploy/helm/lexplatform
helm dependency update
helm upgrade --install lex . -n lexplatform --create-namespace \
  --set publicUrl=https://staging.lexplatform.example \
  --set ingress.host=staging.lexplatform.example \
  --set secrets.jwtPassphrase=$(openssl rand -hex 24) \
  --set secrets.appSecret=$(openssl rand -hex 24)
```

## Production (managed-инфраструктура)

`values-prod.yaml` выключает subcharts и указывает RDS/ElastiCache/managed OpenSearch:

```bash
helm upgrade --install lex . -n lexplatform -f values-prod.yaml \
  --set image.backend.tag=v1.2.0 --set image.frontend.tag=v1.2.0
```

Образы собирает CI по git-тегу `v*` (`docker/php-prod`, `docker/node-prod` → ghcr.io).

## Секреты

Прод: `secrets.existingSecret: lexplatform-secrets` — Secret с ключами
`appSecret, jwtPassphrase, liqpayPublicKey, liqpayPrivateKey, databasePassword, rabbitmqPassword`
создаётся External Secrets Operator (AWS SM / Vault) или SealedSecrets. Блок `secrets.*` из values тогда игнорируется.

JWT-ключи RS256: entrypoint генерирует пару при старте, если её нет. Для стабильности между подами (обязательно при replicas>1!) смонтируйте пару из Secret:

```bash
kubectl -n lexplatform create secret generic lex-jwt \
  --from-file=private.pem --from-file=public.pem
# и добавьте volumeMount на /var/www/backend/config/jwt в backend-deployment
```

## LiqPay в K8s

`server_url` вебхука строится из `publicUrl` — Ingress обязан принимать
`POST /api/v1/payments/webhook/liqpay` из интернета (не закрывайте IP-фильтром: LiqPay не публикует фиксированные диапазоны, безопасность обеспечивает подпись sha1 + сверка суммы).

## Проверка

```bash
helm lint deploy/helm/lexplatform
helm template lex deploy/helm/lexplatform | kubectl apply --dry-run=client -f -
kubectl -n lexplatform get pods,hpa,ingress
curl https://lexplatform.ua/api/health
```

## Обновление без даунтайма

`helm upgrade` → Job миграций (совместимых назад!) → rolling update деплойментов с readiness-пробами. Правило: миграции пишем additive (новые колонки nullable), удаление старого — следующим релизом.
