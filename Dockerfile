FROM heroku/heroku:16

RUN useradd --home-dir /app app

ENV APPLICATION /app/user
ENV PHP_VERSION 5.6.15
ENV HTTPD_VERSION 2.4.17
ENV NGINX_VERSION 1.8.0
ENV PORT 3002
ENV STACK heroku-16
# So we can run PHP in here - Required by our start.sh script used in development
ENV PATH /app/.heroku/php/bin:/app/.heroku/php/sbin:$PATH

WORKDIR $APPLICATION
COPY install.sh /app/user/
RUN ./install.sh

COPY composer.lock /app/user/
COPY composer.json /app/user/

ADD . /app/user/
RUN wget -q -O /heroku-buildpack-php-master.zip https://github.com/heroku/heroku-buildpack-php/archive/master.zip
RUN unzip -q /heroku-buildpack-php-master.zip -d /
RUN /heroku-buildpack-php-master/bin/detect $APPLICATION
RUN /heroku-buildpack-php-master/bin/compile $APPLICATION/ /tmp

EXPOSE $PORT


# FPM socket permissions workaround when run as root
RUN echo "\n\
Group root\n\
" >> /app/.heroku/php/etc/apache2/httpd.conf

# Try setting a longer proxy timeout
RUN echo "\n\
TimeOut 120\n\
ProxyTimeout 120\n\
" >> /app/.heroku/php/etc/apache2/httpd.conf

# FPM socket permissions workaround when run as root
RUN echo "\n\
user nobody root;\n\
" >> /app/.heroku/php/etc/nginx/nginx.conf

# Avoid https://app.getsentry.com/nextftcom/registry/issues/82164697/
RUN echo "always_populate_raw_post_data = -1\n" >> /app/.heroku/php/etc/php/php.ini

RUN chown -R app /app
USER app
CMD PATH=/app/.heroku/php/bin:/app/.heroku/php/sbin:$PATH vendor/bin/heroku-php-apache2 public/
