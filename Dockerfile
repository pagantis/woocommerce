FROM php:7.2-apache

ENV WORDPRESS_VERSION=5.1
ENV WOOCOMMERCE_VERSION=3.6.2

RUN cd /tmp \
    && curl https://es.wordpress.org/wordpress-$WORDPRESS_VERSION-es_ES.tar.gz  -o $WORDPRESS_VERSION-es_ES.tar.gz \
    && tar xf $WORDPRESS_VERSION-es_ES.tar.gz \
    && rm -rf /var/www/html/ \
    && mv wordpress /var/www/html/

RUN cd /tmp \
    && curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /bin/wp

RUN buildDeps="libxml2-dev" \
    && set -x \
    && apt-get update && apt-get install -y \
        unzip \
        $buildDeps \
        less \
        mariadb-client \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libmcrypt-dev \
        pkg-config \
        patch \
        --no-install-recommends && rm -rf /var/lib/apt/lists/*

ADD "https://git.archlinux.org/svntogit/packages.git/plain/trunk/freetype.patch?h=packages/php" /tmp/freetype.patch
RUN docker-php-source extract; \
    cd /usr/src/php; \
    patch -p1 -i /tmp/freetype.patch; \
    rm /tmp/freetype.patch

RUN docker-php-ext-install -j$(nproc) pdo_mysql soap mysqli pdo mbstring zip \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false -o APT::AutoRemove::SuggestsImportant=false $buildDeps

ADD ./config/ /
RUN chmod +x /*.sh

ENTRYPOINT ["/install.sh"]
CMD ["apache2-foreground"]