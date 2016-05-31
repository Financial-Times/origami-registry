#!/usr/bin/env bash

# FOR DEV ONLY - in the `docker-compose.yml` this is set as the entrypoint of
# the `web` process.

# Ensure there are no old PID files left on the filesystem (docker stop
# doesn't trigger the correct signal handlers sometimes so they can be left
# over causing httpd to fail to start later)
rm -rf /run/httpd/* /tmp/httpd* /app/.heroku/php/var/apache2/run/*


# NOTE: Migrations are only run in development.  Database changes to
# production need to be applied with a different, probably manual, method at
# the moment.

# Try and wait for the db connection to become available before running the
# migrations, while docker compose tries make sure that the db process is up
# before the web, ultimately it can't tell when the database has actually
# initialised.  This tries to mitigate that a little
nc -z -q 30 dbmaster 3306
sleep 10
echo "Running migrations"
php $PWD/vendor/ftlabs/migrations/bin/DbMigrate --migrations ./_buildconf/migrations/ --dbname origami-registry --pdo "mysql:dbname=origami-registry;host=dbmaster:3306" --dbhostname dbmaster --password test --username root --verbose


# Start the HTTPD server using Heroku's tools (this is the same value as is in
# the Procfile)
echo "Running apache"
$PWD/vendor/bin/heroku-php-apache2 public/
