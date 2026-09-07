#!/usr/bin/env bash
# Простой деплой на один сервер (MVP, ТЗ §13.2: 10K users).
# Для 100K+ — переход на Kubernetes (см. docs/DEPLOY.md).
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Pull latest code"
git pull --ff-only

echo "→ Build & restart"
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

echo "→ Migrations"
docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

echo "→ Reindex search (async-safe)"
docker compose exec -T app php bin/console app:search-reindex || true

echo "→ Health check"
sleep 3
curl -fsS http://localhost:8080/api/health && echo " ✔ deploy OK"
