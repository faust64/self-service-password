FROM php:7.2-apache
# Install PHP extensions and PECL modules.
RUN buildDeps=" \
        libbz2-dev \
        libsasl2-dev \
        libcurl4-gnutls-dev \
    " \
    runtimeDeps=" \
        curl \
        libicu-dev \
        libldap2-dev \
        libzip-dev \
    " \
    && apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y $buildDeps $runtimeDeps \
    && docker-php-ext-install bcmath bz2 iconv intl mbstring opcache curl \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install ldap \
    && apt-get purge -y --auto-remove $buildDeps \
    && rm -r /var/lib/apt/lists/* \
    && echo "RemoteIPHeader X-Forwarded-For" >/etc/apache2/mods-available/remoteip.conf \
    && for net in 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16; do \
	echo "RemoteIPInternalProxy $net"; \
    done >>/etc/apache2/mods-available/remoteip.conf \
    && a2enmod rewrite remoteip \
    && ln -sfT /dev/stderr /var/log/apache2/error.log \
    && ln -sfT /dev/stdout /var/log/apache2/access.log \
    && ln -sfT /dev/stdout /var/log/apache2/other_vhosts_access.log \
    && sed -i 's| 80| 8080|g' /etc/apache2/ports.conf \
    && ls /etc/apache2/sites-enabled/*.conf 2>/dev/null | while read vhost; do \
        sed -i 's|:80|:8080|g' $vhost; \
    done \
    && if grep ErrorLog /etc/apache2/apache2.conf >/dev/null; then \
	sed -i 's|ErrorLog.*|ErrorLog /dev/stderr|' /etc/apache2/apache2.conf; \
    else \
	echo ErrorLog /dev/stderr >>/etc/apache2/apache2.conf; \
    fi \
    && if grep TransferLog /etc/apache2/apache2.conf >/dev/null; then \
	sed -i 's|TransferLog.*|TransferLog /dev/stdout|' /etc/apache2/apache2.conf; \
    else \
	echo TransferLog /dev/stdout >>/etc/apache2/apache2.conf; \
    fi
RUN mkdir -p /usr/share/php/smarty3/ && \
    curl -Lqs https://github.com/smarty-php/smarty/archive/v3.1.35.tar.gz | \
    tar xzf - -C /usr/share/php/smarty3/ --strip-components=2
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY . /var/www
RUN rmdir /var/www/html && \
    mv /var/www/htdocs /var/www/html && \
    mkdir -p /var/www/templates_c && \
    ( chown -R 1001:root /var/www/templates_c /run /var/log/apache2 2>/dev/null || echo OK ) && \
    ( chmod -R g=u /var/www/templates_c /run /var/log/apache2 2>/dev/null || echo OK )

EXPOSE 8080
USER 1001
