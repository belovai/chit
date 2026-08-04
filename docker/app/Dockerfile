FROM php:8.4-fpm

WORKDIR /var/www

RUN apt-get update && \
apt-get install -y --force-yes --no-install-recommends \
    libmemcached-dev \
    libz-dev \
    libpq-dev \
    libfreetype6-dev \
    libssl-dev \
    libmcrypt-dev \
    libxml2-dev \
    libzip-dev \
    zlib1g-dev \
    openssh-client \
    libmagickwand-dev \
    librabbitmq-dev \
    unzip \
    git \
    cron \
    supervisor \
    vim \
    tesseract-ocr \
    tesseract-ocr-hun \
    tesseract-ocr-eng \
    poppler-utils \
    fonts-dejavu-core \
    ghostscript

# Install intl extention
RUN docker-php-ext-install intl

    # Install the PHP pcntl extention
RUN docker-php-ext-install pcntl

# Install the PHP zip extention
RUN docker-php-ext-install zip

# Install the PHP pdo_pgsql extention
RUN docker-php-ext-install pdo_pgsql

# Install the PHP bcmath extension
RUN docker-php-ext-install bcmath

# Install the PHP opcache extension
RUN docker-php-ext-configure opcache --enable-opcache && docker-php-ext-install opcache

# Install the PHP mcrypt extention
RUN pecl install -f mcrypt && docker-php-ext-enable mcrypt

# Install the PHP redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Imagick — used by the Extraction module to deskew/denoise scans before OCR.
RUN pecl install imagick && docker-php-ext-enable imagick

# Install the PHP swoole extension
# RUN pecl install swoole && docker-php-ext-enable swoole

# Install the PHP xlswriter extension
# RUN pecl install xlswriter && docker-php-ext-enable xlswriter

# Install the PHP amqp extension
# RUN pecl install amqp && docker-php-ext-enable amqp

# Clear cache
# RUN apt-get clean && rm -rf /var/lib/apt/lists/* && rm -R /var/www/*

# Install composer and add its bin to the PATH.
RUN curl -s http://getcomposer.org/installer | php && \
    echo "export PATH=${PATH}:/var/www/vendor/bin" >> ~/.bashrc && \
    mv composer.phar /usr/local/bin/composer
# Source the bash
RUN . ~/.bashrc

COPY ./php.ini /usr/local/etc/php/conf.d/99-custom.ini

ARG UID
ARG GID

RUN usermod -s /bin/bash www-data && \
    usermod -u $UID www-data && \
    groupmod -g $GID www-data

ENTRYPOINT ["/var/www/docker/app/docker-entrypoint.sh"]

EXPOSE 9000
CMD ["start"]
