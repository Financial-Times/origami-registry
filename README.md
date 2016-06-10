# Origami registry

Origami component registry; lists modules and web services with build status details, etc.


## Development set up

To set up a development environment, download and install the docker toolkit (http://docs.docker.com/mac/step_one/).  You'll need `docker-compose` and `docker`.  Or use homebrew:

    brew tap caskroom/homebrew-cask
    brew install brew-cask
    brew cask install docker-machine docker-compose

You may now have to change the owner of your `.docker` directory if the owner is root:

    chown -R `whoami` ~/.docker

Create a virtual machine to run the application's containers. The default size didn't appear to be large enough so this will create one with an increased disk size:

    docker-machine create --driver virtualbox --virtualbox-disk-size "50000" dev

After the first setup, you will only need to start the created machine. (Use the same name as before - in our case `dev`):

    docker-machine start dev

Once started, put the machine's config into your environment. Both right now and on next login:

    docker-machine env dev
    eval $(docker-machine env dev)
    echo "eval $(docker-machine env dev)" >> ~/.profile

Find out the IP address of the machine:

    docker-machine ip dev

When running locally, the app [configuration values](#Configuration) are stored in a local `.env` file and ignored by Git. To set up your local `.env` file, copy the `sample.env` file to `.env` and fill in the missing values from the Origami Registry Configuration note in the shared folder on LastPass.

With the `.env` file setup, in the registry's working directory, run the build and start the app (note: you need to be connected to the internal network in order to install the dependencies from Stash):

    docker-compose build
    docker-compose up

Now you can access the app at the IP address discovered earlier, over HTTP on port 3000, and over MySQL on port 3306.  In your browser go to http://192.168.99.102:3000/ (using the IP given by `docker-machine ip dev`)

To SSH into the web or DB nodes, you first need to SSH into the Docker VM, and then into the container you want:

    docker-machine ssh dev
    docker ps
    docker exec -i -t registry_web_1 bash

## Deploying

You need to authenticate with Heroku (this app is `origami-registry-eu`) and use the Heroku docker plugin: `heroku plugins:install heroku-docker`.

Then, run `git describe --tags > ./appversion; heroku docker:release; rm -f ./appversion`.

See also the [architecture diagram](https://docs.google.com/drawings/d/1dP1nrX6H2VLQoeDt3Y1TWYOTZSUexESY3QUmPupMpxA/edit) in Google drive.

## Running the registry update script on your local machine

To run the update registry script on your local machine connect to the docker-machine:

    docker-machine ssh dev
    docker ps
    docker exec -i -t registry_web_1 bash
    cd app/scripts
    php updateregistry

## Orchestration files

The following files are used in build, test and deploy automation:

* `.dockerignore`: used to ignore things when adding files to the Docker image.  Generally this will be the same as the `.gitignore` file as the build happens at the container creation time.  See the `Dockerfile` for more info.
* `app.json`: TODO
* `Dockerfile`: TODO
* `docker-compose.yml': TODO
* `start.sh`: TODO

## Configuration

In dev, these are configured in docker-compose.yml.  In live, it's `heroku config`

* `PORT`: Port used by Apache to serve HTTP traffic.  Must match container's exposed ports config.  Should not be configured explicitly on Heroku
* `DATABASE_URL`: URL of the MySQL instance to use.  In dev, this is a linked container, in live, it's a ClearDB addon (TODO: Not sure why we can't allow Heroku to simulate the ClearDB container in dev)
* `SENTRY_DSN`: URL of the Sentry project to use to collect runtime errors, exceptions and log messages
* `IS_DEV`: Boolean to indicate whether the app should be considered to be running in a dev environment.  If true, will suppress some notifications and change error reporting behaviour.
* `SLACK_WEBHOOK`: "Incoming Webhook" URL from Slack to which to post notifications of new discovered modules
* `SLACK_CHANNEL`: Slack channel to post new module notifications in
* `BUILD_SERVICE_HOST`: Hostname of the build service to use for fetching module metadata
* `VIEW_CACHE_PATH`: Path on disk to use to cache view templates in Twig
* `DEBUG_KEY`: String, if set in a `Debug` HTTP header, will set dev mode to true for that request only.
