SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose

.PHONY: help up down api-shell test test-api test-web e2e lint fix migrate openapi seed signing-key

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Start the local environment
	$(COMPOSE) up -d --build
	$(COMPOSE) exec php composer install --no-interaction --no-progress

down: ## Stop the local environment
	$(COMPOSE) down

api-shell: ## Shell into the PHP container
	$(COMPOSE) exec php bash

test: test-api test-web ## Run all tests (api + web)

test-api: ## Run PHPUnit
	$(COMPOSE) exec php vendor/bin/phpunit

test-web: ## Run Vitest
	$(COMPOSE) exec node pnpm test

e2e: ## Run Playwright e2e tests (login -> create company -> switch company)
	$(COMPOSE) exec node pnpm exec playwright install --with-deps chromium
	$(COMPOSE) exec -e E2E_BASE_URL=http://localhost:5173 node pnpm e2e

lint: ## Run PHP-CS-Fixer (check), PHPStan, Deptrac, ESLint, tsc
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi
	$(COMPOSE) exec php bin/console cache:clear --env=dev
	$(COMPOSE) exec php vendor/bin/phpstan analyse
	$(COMPOSE) exec php vendor/bin/deptrac analyse --no-interaction
	$(COMPOSE) exec node pnpm lint
	$(COMPOSE) exec node pnpm typecheck

fix: ## Auto-fix code style
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --ansi
	$(COMPOSE) exec node pnpm lint:fix
	$(COMPOSE) exec node pnpm format

migrate: ## Run Doctrine migrations as the migration owner role
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

openapi: ## Dump OpenAPI spec to api/openapi.json and regenerate the web client
	$(COMPOSE) exec php bin/console nelmio:apidoc:dump --format=json --no-interaction > api/openapi.json
	$(COMPOSE) exec node pnpm generate-client

seed: ## Load development fixtures
	$(COMPOSE) exec php bin/console app:seed --no-interaction

signing-key: ## Generate the dev document-signing RSA key (Despacho 8632/2014 §6.2: 1024-bit)
	$(COMPOSE) exec php mkdir -p var/signing
	$(COMPOSE) exec php openssl genrsa -out var/signing/document-signing-dev.pem 1024
