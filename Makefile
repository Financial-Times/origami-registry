deploy: build
	@git describe --tags > ./appversion
	@heroku docker:release --app origami-registry-eu
	@rm -f ./appversion

build:
	@if [[ "$$(docker-machine ls | grep dev)" == *"Stopped"* ]]; then make _docker-start; else echo "Docker machine already running"; fi

install:
	@if [[ "$$(docker-machine ls | grep dev)" != *"dev"* ]]; then make _docker-create; else echo "Docker machine already created"; fi

build-dev: build
	@docker-compose build

run-dev:
	@docker-compose up

_docker-create:
	@docker-machine create --driver virtualbox --virtualbox-disk-size "50000" dev
	@make _docker-env

_docker-start:
	@docker-machine start dev
	@make _docker-env

_docker-env:
	@docker-machine env dev
	@eval $(docker-machine env dev)
	@echo "eval $(docker-machine env dev)" >> ~/.profile

.PHONY: build