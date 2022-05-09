FROM debian:bullseye

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get -qq update && \
    apt-get -qq dist-upgrade && \
    apt-get -qq install -y apt-transport-https lsb-release ca-certificates curl

RUN curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg && \
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/php.list && \
    apt-get -qq update

RUN apt-get -qq install -y \
        php8.1-cli \
        php8.1-bcmath \
        php8.1-curl \
        php8.1-gd \
        php8.1-igbinary \
        php8.1-imagick \
        php8.1-imap \
        php8.1-intl \
        php8.1-mbstring \
        php8.1-mysql \
        php8.1-readline \
        php8.1-soap \
        php8.1-xml \
        php8.1-zip \
        php8.1-zstd \
        composer

RUN apt-get -qq install -y php8.1-ast

RUN mkdir /opt/phan && \
    composer require -d /opt/phan phan/phan && \
    ln -snrf /opt/phan/vendor/bin/phan /usr/local/bin/phan

COPY entrypoint.sh /entrypoint.sh
COPY submit.php /submit.php

ENTRYPOINT ["/entrypoint.sh"]
