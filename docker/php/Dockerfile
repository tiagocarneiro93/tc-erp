FROM dunglas/frankenphp:1-php8.4-bookworm

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# openssl and zip's zlib dependency ship with the base image; the rest are
# required by CLAUDE.md's stack list. redis is used by the health check
# (task 0.3) and later by Messenger/rate-limiter (task 0.5).
RUN install-php-extensions \
    intl \
    pdo_pgsql \
    bcmath \
    sodium \
    zip \
    opcache \
    redis

ARG INSTALL_XDEBUG=false
RUN if [ "$INSTALL_XDEBUG" = "true" ]; then install-php-extensions xdebug; fi

# curl is used by the container healthcheck once the health endpoint exists (task 0.3).
RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
