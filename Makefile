install: node_modules bower_components

build-dev: build
	@./node_modules/.bin/obt build --js=./public/js/main.js --sass=./public/scss/main.scss --env=production --buildFolder=./public

watch-dev:
	@./node_modules/.bin/obt build --watch --js=./public/js/main.js --sass=./public/scss/main.scss --env=production --buildFolder=./public

node_modules: package.json
	@echo "Running npm install"
	@npm install

bower_components: bower.json
	@echo "Running bower install"
	@bower install

# These tasks have been intentionally left blank
package.json:
bower.json:

.PHONY: build
