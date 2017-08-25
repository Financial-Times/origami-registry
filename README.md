Origami registry
================

Origami component registry; lists modules and web services with build status details, etc.

Table of Contents
-----------------

  * [Requirements](#requirements)
  * [Running Locally](#running-locally)
  * [Configuration](#configuration)
  * [Deploying](#deploying)
  * [Trouble-Shooting](#trouble-shooting)
  * [License](#license)


Requirements
------------

To set up a development environment, download and install docker for mac (https://store.docker.com/editions/community/docker-ce-desktop-mac).


Running locally
---------------

Before we can run the application, we'll need to create a `.env` file. You can copy the `sample.env` file to `.env` and fill in the missing values from the Origami Registry Configuration note in the shared folder on LastPass.

Use `docker-compose` to build and start the container:

```sh
docker-compose build
docker-compose up
```

Now you can access the app over HTTP on port `3000`.

```sh
open "localhost:3000"
```

The MySQL database is accessible on port 3306, the settings for which are in the `.env` file.

### Working with local assets

We have a series of Make tasks to simplify working with local assets. To install all the dependencies from `npm` and `bower` run:

```sh
make install
```

To compile all assets you can run:

```sh
make build-dev
```

And finally to watch and compile front-end assets during development, you can run:

```sh
make watch-dev
```

### Setting up a local database

To work with the Registry locally you will probably need some data in your local database. To do this you can run the update registry script on your local machine - **warning**, this will take several hours to run for the first time locally.

To run the update script locally, you will need to SSH into the Docker VM and the container for the Registry:

```sh
docker ps
docker exec -i -t origamiregistry_web_1 bash
```

You should now be in a bash command line for the registry app. You can now run the update registry script with the following command:

```sh
php ./app/scripts/updateregistry
```


Deploying
---------

You need to authenticate with Heroku (this app is `origami-registry-eu`) and use the Heroku container plugin: `heroku plugins:install heroku-container-tools`. Then run the following to push the lastest changes to production:

```sh
git describe --tags > ./appversion && heroku container:push web --app origami-registry-eu && rm -f ./appversion
```

### Deploying to QA

To update the QA version of the Registry, use the same process as above but deploy to the app `origami-registry-qa` instead:

```sh
git describe --tags > ./appversion && heroku container:push web --app origami-registry-qa && rm -f ./appversion
```

### Architecture

The Origami Registry [architecture diagram](https://docs.google.com/drawings/d/1dP1nrX6H2VLQoeDt3Y1TWYOTZSUexESY3QUmPupMpxA/edit) can be found in Google drive.


Trouble-Shooting
----------------

We've outlined some common issues that can occur when running the Registry locally:

### What do I do if the dependencies won't install?

This is likely because you're on a different network which doesn't allow you access to the private FT repositories. Make sure you're connected to the internal network/wifi, to make sure `docker-compose` has access to the correct network.

### The discovery script has stopped running hourly on production.

There is a row in the `meta` table of the database (key: `cron_running_host`) that will prevent the discover script from running more than once at any given time. If the discover script has stopped running this is likely because a previous update didn't finish or failed while running and the `cron_running_host` row did not get deleted from the table. To fix this, log in to the production database (login details are in Heroku config vars: `CLEARDB_DATABASE_URL`), and delete the `cron_running_host` row from the `meta` table.

### A new component is not showing up in the Registry.

If a new component has been created or one has been updated to be an Origami component, the Registry might not pick it up as an Origami component on the next run of the discovery script like it does with new releases. This is due to how often the Registry scans Origami components vs. non-Origami components. The Registry scans all repositories in the Git sources hourly, but only looks at new releases of already valid Origami components, whereas non-Origami components are checked only every 48 hours.

To speed up the discovery of a new Origami component, update the module in the `components` table of the database by searching the `module_name` and changing the `is_origami` column to `1`.

### The Origami Sensei has stopped announcing new releases.

The Origami Sensei is a Slack Webhook that announces new releases into the [FT Origami](https://financialtimes.slack.com/messages/ft-origami/) Slack channel. It's configured in the Slack Apps settings, by going to `Custom Integrations` > `Incoming Webhooks` and searching for `Origami`. If it's stopped announcing in the channel it's likely that the webhook has become disabled and will need to be re-enabled.

There are also settings for which webhook to point to in the Heroku config vars: `SLACK_WEBHOOK` and `SLACK_CHANNEL`.

Configuration
-------------

In dev, these are configured in the `.env` file.  In live, it's `heroku config`

* `PORT`: Port used by Apache to serve HTTP traffic.  Must match container's exposed ports config.  Should not be configured explicitly on Heroku
* `DATABASE_URL`: URL of the MySQL instance to use.  In dev, this is a linked container, in live, it's a ClearDB addon (TODO: Not sure why we can't allow Heroku to simulate the ClearDB container in dev)
* `SENTRY_DSN`: URL of the Sentry project to use to collect runtime errors, exceptions and log messages
* `IS_DEV`: Boolean to indicate whether the app should be considered to be running in a dev environment.  If true, will suppress some notifications and change error reporting behaviour.
* `GITHUB_CREDENTIALS`: Used to connect to Github for the component discovery process.
* `SLACK_WEBHOOK`: "Incoming Webhook" URL from Slack to which to post notifications of new discovered modules
* `SLACK_CHANNEL`: Slack channel to post new module notifications in
* `BUILD_SERVICE_HOST`: Hostname of the build service to use for fetching module metadata
* `VIEW_CACHE_PATH`: Path on disk to use to cache view templates in Twig
* `DEBUG_KEY`: String, if set in a `Debug` HTTP header, will set dev mode to true for that request only.

### Orchestration files

The following files are used in build, test and deploy automation:

* `.dockerignore`: used to ignore things when adding files to the Docker image.  Generally this will be the same as the `.gitignore` file as the build happens at the container creation time.  See the `Dockerfile` for more info.
* `app.json`: TODO
* `Dockerfile`: TODO
* `docker-compose.yml': TODO
* `start.sh`: TODO

License
-------

The Financial Times has published this software under the [MIT license][license].

[license]: http://opensource.org/licenses/MIT
