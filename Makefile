# Project NEXUS — Canonical Development Commands
# Usage: make <target>
# Run `make help` for available targets.

.PHONY: help migrate migrate-dry migrate-prod migrate-prod-dry backup-prod-db drift-check dev build test lint

# ─────────────────────────────────────────────────────────────
# Help
# ─────────────────────────────────────────────────────────────
help: ## Show this help
	@echo ""
	@echo "Project NEXUS — Make Targets"
	@echo "────────────────────────────────────────────"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}'
	@echo ""

# ─────────────────────────────────────────────────────────────
# Database Migrations
# ─────────────────────────────────────────────────────────────

migrate: ## Run migration locally (usage: make migrate FILE=2026_02_22_example.sql)
ifndef FILE
	$(error FILE is required. Usage: make migrate FILE=2026_02_22_example.sql)
endif
	@echo "╔═══════════════════════════════════════════╗"
	@echo "║  LOCAL MIGRATION                          ║"
	@echo "╚═══════════════════════════════════════════╝"
	docker exec nexus-php-app php scripts/safe_migrate.php --file=$(FILE)

migrate-dry: ## Dry-run migration locally (usage: make migrate-dry FILE=2026_02_22_example.sql)
ifndef FILE
	$(error FILE is required. Usage: make migrate-dry FILE=2026_02_22_example.sql)
endif
	docker exec nexus-php-app php scripts/safe_migrate.php --file=$(FILE) --dry-run

migrate-prod: ## Run migration on production (usage: make migrate-prod FILE=2026_02_22_example.sql)
ifndef FILE
	$(error FILE is required. Usage: make migrate-prod FILE=2026_02_22_example.sql)
endif
	@echo "╔═══════════════════════════════════════════╗"
	@echo "║  PRODUCTION MIGRATION                     ║"
	@echo "║  This will modify the production database ║"
	@echo "╚═══════════════════════════════════════════╝"
	bash scripts/migrate-production.sh $(FILE)

migrate-prod-dry: ## Dry-run migration on production (usage: make migrate-prod-dry FILE=2026_02_22_example.sql)
ifndef FILE
	$(error FILE is required. Usage: make migrate-prod-dry FILE=2026_02_22_example.sql)
endif
	bash scripts/migrate-production.sh $(FILE) --dry-run

backup-prod-db: ## Take a timestamped backup of the production database
	bash scripts/backup-production-db.sh

drift-check: ## Compare migration history between local and production
	bash scripts/check-migration-drift.sh

# ─────────────────────────────────────────────────────────────
# Development
# ─────────────────────────────────────────────────────────────

dev: ## Start all Docker services
	docker compose up -d

build: ## Build React frontend for production
	cd react-frontend && npm run build

test: ## Run all tests (PHP + React)
	docker exec nexus-php-app vendor/bin/phpunit --testsuite=Unit
	cd react-frontend && npm test -- --run

lint: ## TypeScript check
	cd react-frontend && npx tsc --noEmit
