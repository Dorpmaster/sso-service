ROOT_DIR       := $(shell dirname $(realpath $(lastword $(MAKEFILE_LIST))))
SHELL          := $(shell which bash)
PROJECT_NAME    = entrypoint-sso
ARGS            = $(filter-out $@,$(MAKECMDGOALS))

.SILENT: ;               # no need for @
.ONESHELL: ;             # recipes execute in same shell
.NOTPARALLEL: ;          # wait for this target to finish
.EXPORT_ALL_VARIABLES: ; # send all vars to shell
default: help-default;   # default target
Makefile: ;              # skip prerequisite discovery

help-default help:
	@echo "help: Shows Help menu"
	@echo "status: Shows containers status"
	@echo "up: Create and start application in detached mode (in the background)"
	@echo "stop: Stop application"
	@echo "down: Stop application and remove all containers"
	@echo "root:  Login to the PHP container as 'root' user"
	@echo "root-caddy:  Login to the Caddy container as 'root' user"
	@echo "build: Build or rebuild services"
	@echo "logs: Attach to logs"
	@echo "logs-caddy: Attach to Caddy logs"
	@echo "stop-workers: Stops background workers"
	@echo "start-workers: Starts background workers"
	@echo "build-prod: Build production image"
	@echo "up-prod: Creates and starts application from production image"
	@echo "stop-prod: Stops production application"
	@echo "down-prod: Stops production application and remove all containers"
	@echo ""

status:
	docker-compose --project-name $(PROJECT_NAME) ps

up:
	docker-compose --project-name $(PROJECT_NAME) up -d
	docker exec -u root $$(docker-compose --project-name $(PROJECT_NAME) ps -q php) sh -c "/srv/app/bin/stop-workers.sh"

stop:
	docker-compose --project-name $(PROJECT_NAME) stop

down:
	docker-compose --project-name $(PROJECT_NAME) down

root:
	docker exec -it -u root $$(docker-compose --project-name $(PROJECT_NAME) ps -q php) /bin/sh

build:
	docker-compose --project-name $(PROJECT_NAME) build

logs:
	docker logs -f $$(docker-compose --project-name $(PROJECT_NAME) -f docker-compose.yml ps -q php)

logs-caddy:
	docker logs -f $$(docker-compose --project-name $(PROJECT_NAME) -f docker-compose.yml ps -q caddy)

root-caddy:
	docker exec -it -u root $$(docker-compose --project-name $(PROJECT_NAME) ps -q caddy) /bin/sh

stop-workers:
	docker exec -u root $$(docker-compose --project-name $(PROJECT_NAME) -f docker-compose.yml ps -q php) sh -c "/srv/app/bin/stop-workers.sh"

start-workers:
	docker exec -u root $$(docker-compose --project-name $(PROJECT_NAME) -f docker-compose.yml ps -q php) sh -c "/srv/app/bin/start-workers.sh"

build-prod:
	docker-compose --project-name $(PROJECT_NAME)-prod -f docker-compose.prod.yml build

up-prod:
	docker-compose --project-name $(PROJECT_NAME)-prod -f docker-compose.prod.yml up -d --remove-orphans

stop-prod:
	docker-compose --project-name $(PROJECT_NAME)-prod -f docker-compose.yml stop

down-prod:
	docker-compose --project-name $(PROJECT_NAME)-prod -f docker-compose.yml down
