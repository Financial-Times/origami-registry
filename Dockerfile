FROM heroku/php

# Avoid https://app.getsentry.com/nextftcom/registry/issues/82164697/
RUN echo "always_populate_raw_post_data = -1\n" >> /app/.heroku/php/etc/php/php.ini
