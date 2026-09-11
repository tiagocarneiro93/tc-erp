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
	@echo "test-api: /api has no Symfony app yet (task 0.3)"

test-web: ## Run Vitest
	@echo "test-web: /web has no app yet (task 0.13)"

e2e: ## Run Playwright e2e tests
	@echo "e2e: /web has no app yet (task 0.13)"

lint: ## Run PHP-CS-Fixer (check), PHPStan, Deptrac, ESLint, tsc
	@echo "lint: tooling not configured yet (task 0.4)"

fix: ## Auto-fix code style
	@echo "fix: tooling not configured yet (task 0.4)"

migrate: ## Run Doctrine migrations as the migration owner role
	@echo "migrate: /api has no Symfony app yet (task 0.3)"

openapi: ## Dump OpenAPI spec to api/openapi.json and regenerate the web client
	@echo "openapi: not available yet (tasks 0.11, 0.13)"

seed: ## Load development fixtures
	@echo "seed: not available yet (task 0.12)"
