SHELL := /bin/bash
.DEFAULT_GOAL := help

COMPOSE := docker compose

.PHONY: help up down api-shell test test-api test-web e2e lint fix migrate openapi seed

help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Start the local environment
	$(COMPOSE) up -d --build

down: ## Stop the local environment
	$(COMPOSE) down

api-shell: ## Shell into the PHP container
	$(COMPOSE) exec php bash

test: test-api test-web ## Run all tests (api + web)

test-api: ## Run PHPUnit
	$(COMPOSE) exec php vendor/bin/phpunit

test-web: ## Run Vitest
	@echo "test-web: /web has no app yet (task 0.13)"

e2e: ## Run Playwright e2e tests
	@echo "e2e: /web has no app yet (task 0.13)"

lint: ## Run PHP-CS-Fixer (check), PHPStan, Deptrac, ESLint, tsc
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --dry-run --diff --ansi
	$(COMPOSE) exec php bin/console cache:clear --env=dev
	$(COMPOSE) exec php vendor/bin/phpstan analyse
	$(COMPOSE) exec php vendor/bin/deptrac analyse --no-interaction
	@echo "lint (web): not available yet (task 0.13)"

fix: ## Auto-fix code style
	$(COMPOSE) exec php vendor/bin/php-cs-fixer fix --ansi
	@echo "fix (web): not available yet (task 0.13)"

migrate: ## Run Doctrine migrations as the migration owner role
	$(COMPOSE) exec php bin/console doctrine:migrations:migrate --no-interaction

openapi: ## Dump OpenAPI spec to api/openapi.json and regenerate the web client
	$(COMPOSE) exec php bin/console nelmio:apidoc:dump --format=json --no-interaction > api/openapi.json
	@echo "openapi: web client regeneration not available yet (task 0.13 — no /web app exists to generate into)"

seed: ## Load development fixtures
	$(COMPOSE) exec php bin/console app:seed --no-interaction
