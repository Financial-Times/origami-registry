Origami registry
================

Origami component registry; lists modules and web services with build status details, etc.

Table of Contents
-----------------

  * [Requirements](#requirements)
  * [Running Locally](#running-locally)
  * [Configuration](#configuration)
  * [Deployment](#deployment)
  * [Trouble-Shooting](#trouble-shooting)
  * [License](#license)


Requirements
------------

To set up a development environment, download and install the docker toolkit (https://docs.docker.com/engine/getstarted/step_one/).  You'll need `docker-compose` and `docker`.  Or use homebrew:

```sh
brew tap caskroom/homebrew-cask
brew install brew-cask
brew cask install docker-machine docker-compose
```

You may now have to change the owner of your `.docker` directory if the owner is root:

```sh
chown -R `whoami` ~/.docker
```

You'll also need `gulp` installed globally to compile the front-end assets:

```sh
npm install -g gulp
```

Create a virtual machine to run the application's containers using `docker-machine`. The default size isn't large enough, so this will create one with an increased disk size:

```sh
docker-machine create --driver virtualbox --virtualbox-disk-size "50000" dev
```


Running locally
---------------

Before we can run the application, we'll need to create a `.env` file. You can copy the `sample.env` file to `.env` and fill in the missing values from the Origami Registry Configuration note in the shared folder on LastPass.

In the working directory, use `docker-machine` to start the development machine (if you've just created the machine you skip this step):

```sh
docker-machine start dev
```

Once started, put the machine's config into your environment. Both right now and on next login:

```sh
docker-machine env dev
eval $(docker-machine env dev)
echo "eval $(docker-machine env dev)" >> ~/.profile
```

*depending on your machine you might need to remove the `$` in the `eval` statement, after running the first line, there will be instructions in your command line.

Then use `docker-compose` to build and start the container:

```sh
docker-compose build
docker-compose up
```

Now you can access the app over HTTP on port `3000`. If you're on a Mac, you'll need to use the IP of your Docker Machine, which you can get by running `docker-machine ip dev`:

```sh
open "http://$(docker-machine ip default):3000/"
```

The MySQL database is accessible on port 3306, the settings for which are in the `.env` file.

To watch and compile front-end assets during development, you can run:

```sh
make watch-dev
```

### Setting up a local database

To work with the Registry locally you will probably need some data in your local database. To do this you can run the update registry script on your local machine - **warning**, this will take several hours to run for the first time locally.

To run the update script locally, you will need to SSH into the Docker VM and the container for the Registry:

```sh
docker-machine ssh dev
docker ps
docker exec -i -t origamiregistry_web_1 bash
```

You should now be in a bash command line for the registry app. You can now run the update registry script with the following command:

```sh
php ./app/scripts/updateregistry
```


Deploying
---------

You need to authenticate with Heroku (this app is `origami-registry-eu`) and use the Heroku docker plugin: `heroku plugins:install heroku-docker`.

Then, run `make deploy`.

See also the [architecture diagram](https://docs.google.com/drawings/d/1dP1nrX6H2VLQoeDt3Y1TWYOTZSUexESY3QUmPupMpxA/edit) in Google drive.


Trouble-Shooting
----------------

We've outlined some common issues that can occur when running the Registry locally:

### What do I do if the dependencies won't install?

This is likely because you're on a different network which doesn't allow you access to the private FT repositories. Make sure you're connected to the internal network/wifi, then restart the `docker-machine` to make sure `docker-compose` has access to the correct network.

### What can I do to fix `docker-compose` not starting?

If you get an error when running `docker-compose up` the easiest solution is to kill the `docker-machine` and start the setup process again. Run `docker-machine kill dev` to stop the machine. Then make sure you're on the internal network, and start the [Running locally](#running-locally) process from the beginning.


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
