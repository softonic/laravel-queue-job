FROM composer/composer:1.10.23

RUN apk add --no-cache icu-dev && \
    docker-php-ext-install sockets && \
	docker-php-ext-install intl \
