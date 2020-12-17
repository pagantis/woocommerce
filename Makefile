#!/usr/bin/env make
-include .env

SHELL:=/usr/bin/bash

define setup_env
	$(eval ENV_FILE := .env)
	@echo " - setup env .env"
	$(eval include .env)
	$(eval export sed 's/=.*//' .env)
endef
.PHONY: load_dev_env
load_dev_env: ## load .env
		$(call setup_env)


.PHONY: test
test:
# https://unix.stackexchange.com/a/594401
	$(call load_dev_env)
	@echo ${PROJECT_NAME}


.PHONY: dev
dev:
	@docker-compose build --no-cache db
	@docker-compose up -d --force-recreate --build woocommerce-dev

.PHONY: setup
setup:
	vendor/bin/phpunit --group woocommerce3-setup

.PHONY: down
down:
	@docker-compose down --volumes

.PHONY: clean
clean:
	@echo "💥 Stopping Containers"
	@docker-compose down --volumes --remove-orphans --rmi local

.PHONY: reset
reset: clean load_dev_env dev

.PHONY: prune_force
prune_force:
	@docker volume prune --force
