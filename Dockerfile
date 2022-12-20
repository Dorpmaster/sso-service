# https://docs.docker.com/engine/reference/builder/#understand-how-arg-and-from-interact
ARG PHP_VERSION=8.2
ARG CADDY_VERSION=2

# "php" stage
FROM php:${PHP_VERSION}-fpm-alpine AS symfony_php

# persistent / runtime deps
RUN apk add --no-cache \
	acl \
	fcgi \
	file \
	gettext \
	git \
	gnu-libiconv \
	supervisor \
;

# install gnu-libiconv and set LD_PRELOAD env to make iconv work fully on Alpine image.
# see https://github.com/docker-library/php/issues/240#issuecomment-763112749
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so

ARG APCU_VERSION=5.1.21
RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
        linux-headers \
		icu-dev \
		libzip-dev \
		zlib-dev \
		rabbitmq-c-dev \
	; \
	\
	docker-php-ext-configure zip; \
	docker-php-ext-install -j$(nproc) \
		intl \
		zip \
	; \
	pecl install \
		apcu-${APCU_VERSION} \
	; \
	pecl install \
	    xdebug \
	; \
	pecl install \
    	amqp \
    	; \
    pecl install \
        redis \
        ; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		opcache \
		amqp \
		redis \
		xdebug \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .phpexts-rundeps $runDeps; \
	\
	apk del .build-deps

COPY docker/php/docker-healthcheck.sh /usr/local/bin/docker-healthcheck
RUN chmod +x /usr/local/bin/docker-healthcheck

HEALTHCHECK --interval=10s --timeout=3s --retries=3 CMD ["docker-healthcheck"]

RUN ln -s $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini
COPY docker/php/conf.d/symfony.dev.ini $PHP_INI_DIR/conf.d/symfony.ini

COPY docker/php/conf.d/docker-php-ext-xdebug.ini $PHP_INI_DIR/conf.d/docker-php-ext-xdebug.ini

COPY docker/php/php-fpm.d/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

COPY docker/php/supervisord.conf /etc/supervisord.conf

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

VOLUME /var/run/php

COPY --from=composer/composer:2-bin /composer /usr/bin/composer

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

ENV PATH="${PATH}:/root/.composer/vendor/bin"

WORKDIR /srv/app

COPY . .

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer install -o --ignore-platform-req=php; \
	chmod +x bin/console; \
	ln -s /usr/local/bin/docker-entrypoint.sh; \
	sync

# RUN set -eux; \
# 	mkdir -p var/cache var/log; \
# 	ln -s /usr/local/bin/docker-entrypoint; \
# 	sync

VOLUME /srv/app/var

ENTRYPOINT ["sh", "/usr/local/bin/docker-entrypoint"]
CMD ["docker-entrypoint"]

FROM caddy:2-builder-alpine AS symfony_caddy_builder

RUN xcaddy build

FROM caddy:2-alpine AS symfony_caddy

WORKDIR /srv/app

COPY --from=symfony_caddy_builder /usr/bin/caddy /usr/bin/caddy
COPY --from=symfony_php /srv/app/public public/
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
