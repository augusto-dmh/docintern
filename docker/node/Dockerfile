FROM node:20-alpine

WORKDIR /var/www/html

RUN apk add --no-cache \
    git \
    php83 \
    php83-bcmath \
    php83-ctype \
    php83-curl \
    php83-dom \
    php83-fileinfo \
    php83-mbstring \
    php83-openssl \
    php83-pcntl \
    php83-pdo \
    php83-pdo_mysql \
    php83-pecl-redis \
    php83-session \
    php83-simplexml \
    php83-sockets \
    php83-tokenizer \
    php83-xml \
    php83-zip \
    && ln -sf /usr/bin/php83 /usr/bin/php

EXPOSE 5173
CMD ["sh", "-lc", "if [ ! -x node_modules/.bin/vite ]; then npm install; fi && npm run dev -- --host 0.0.0.0"]
