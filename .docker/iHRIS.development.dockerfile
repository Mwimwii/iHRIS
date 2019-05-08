#Download base image ubuntu 16.04
FROM ubuntu:16.04
LABEL author="Chisanga L. Siwale <Chisanga.Siwale@moh.gov.zm>"

# Apache ENVs
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_PID_FILE /var/run/apache2/apache2.pid
ENV APACHE_SERVER_NAME localhost

RUN set -eux; \
	apt-get update; \
    apt-get install -y apache2

RUN mkdir -p /var/lib/iHRIS/lib/4.3.3
RUN ln -s /var/lib/iHRIS/lib/4.3.0 /var/lib/iHRIS/lib/4.3

COPY ./.docker/config/apache2.conf /etc/apache2/apache2.conf
RUN ln -s /var/lib/iHRIS/lib/4.3.3/ihris-manage/sites/blank/pages /var/www/html/manage

#Installing PHP Packages
RUN set -eux; \
    apt-get install -y \
    php7.0 \
    libapache2-mod-php7.0 \
    php7.0-cli \
    php7.0-common \
    php7.0-mbstring \
    php7.0-gd \
    php7.0-intl \
    php7.0-xml \
    php7.0-mysql \
    php7.0-mcrypt \
    php7.0-zip \
    php-pear \
    php-gd \
    php-tidy \
    php-intl \
    php-bcmath \
    php-text-password \
    php-mbstring \
    php-uuid \
    php-memcached \
    memcached \
    php-apcu

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

#Set ZendOpcache options
COPY ./.docker/config/opcache.ini /etc/php/7.0/mods-available/opcache.ini

#UUID
COPY ./.docker/config/uuid.ini /etc/php/7.0/mods-available/uuid.ini
RUN ln -s /etc/php/7.0/mods-available/uuid.ini /etc/php/7.0/apache2/conf.d/20-uuid.ini
RUN ln -s /etc/php/7.0/mods-available/uuid.ini /etc/php/7.0/cli/conf.d/20-uuid.ini

#APCu
COPY ./.docker/config/apcu.ini /etc/php/7.0/mods-available/apcu.ini

RUN a2enmod rewrite

EXPOSE 80

VOLUME ["$APACHE_LOG_DIR"]

#CMD ["/usr/bin/memcached", "-u", "memcache"]
#CMD ["memcached", "start", "memcache"]
CMD ["/usr/sbin/apache2ctl", "-DFOREGROUND"]

#COPY ./.docker/memcached.sh memcached.sh
#CMD ["memcached.sh"]