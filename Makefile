deploy: build
	@git describe --tags > ./appversion
	@heroku docker:release --app origami-registry-eu
	@rm -f ./appversion

build:
	@if [[ "$$(docker-machine ls | grep dev)" == *"Stopped"* ]]; then make _docker-start; else echo "Docker machine already running"; fi

install: node_modules bower_components
	@if [[ "$$(docker-machine ls | grep dev)" != *"dev"* ]]; then make _docker-create; else echo "Docker machine already created"; fi

build-dev: build
	@docker-compose build
	@obt build --js=./public/js/main.js --sass=./public/scss/main.scss --env=production --buildFolder=./public

run-dev:
	@docker-compose up

watch-dev:
	@obt build --watch --js=./public/js/main.js --sass=./public/scss/main.scss --env=production --buildFolder=./public

node_modules:
	@echo "Running npm install"
	@npm install

bower_components:
	@echo "Running bower install"
	@bower install

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
