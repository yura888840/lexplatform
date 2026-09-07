.PHONY: init up down build install-backend install-frontend jwt-keys db-migrate db-fixtures search-index logs test lint help

help: ## Список команд
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-18s\033[0m %s\n", $$1, $$2}'

init: ## Первый запуск: env + build + install + миграции + фикстуры + индекс
	@test -f .env || cp .env.example .env
	docker compose build
	docker compose up -d postgres redis rabbitmq opensearch minio mailhog
	$(MAKE) install-backend
	$(MAKE) jwt-keys
	docker compose up -d
	sleep 5
	$(MAKE) db-migrate
	$(MAKE) db-fixtures
	docker compose exec -T app php bin/console app:seed-legal-base
	docker compose exec -T app php bin/console app:seed-practice-areas
	$(MAKE) search-index
	@echo "✔ Готово: frontend http://localhost:3000 | API http://localhost:8080/api/v1 | docs http://localhost:8080/api/docs"

up: ## Запустить все сервисы
	docker compose up -d

down: ## Остановить
	docker compose down

build: ## Пересобрать образы
	docker compose build

install-backend: ## composer install
	docker compose run --rm app composer install --no-interaction

install-frontend: ## npm install
	docker compose run --rm node npm install

jwt-keys: ## Сгенерировать ключи для JWT (lexik)
	docker compose run --rm app php bin/console lexik:jwt:generate-keypair --skip-if-exists

db-migrate: ## Применить миграции
	docker compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction

db-fixtures: ## Демо-данные (юристы, категории, статьи)
	docker compose exec -T app php bin/console app:seed-demo

search-index: ## Создать индексы OpenSearch и проиндексировать данные
	docker compose exec -T app php bin/console app:search-reindex

logs: ## Логи приложения
	docker compose logs -f app worker

test: ## PHPUnit
	docker compose exec -T app php bin/phpunit

lint: ## Статический анализ
	docker compose exec -T app vendor/bin/phpstan analyse src --level=6 || true
	docker compose run --rm node npm run lint
