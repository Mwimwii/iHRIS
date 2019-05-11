#Download base image ubuntu 18.04
FROM ubuntu:18.04
LABEL author="Chisanga L. Siwale <Chisanga.Siwale@moh.gov.zm>"

# Apache ENVs
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_LOCK_DIR /var/lock/apache2
ENV APACHE_LOG_DIR /var/log/apache2
ENV APACHE_PID_FILE /var/run/apache2/apache2.pid
ENV APACHE_SERVER_NAME localhost

ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
wget gpg dirmngr gpg-agent build-essential checkinstall tk-dev \
libreadline-gplv2-dev libncursesw5-dev libssl-dev libsqlite3-dev \
libgdbm-dev libc6-dev libbz2-dev apt-utils

#Install apache2
RUN apt-get install -y apache2

#Installing PHP Packages
RUN apt-get install -y php-pear php-gd php-tidy php-intl php-bcmath php-text-password php-mbstring php-uuid

#APCu
RUN apt-get install -y php-apcu
COPY ./.docker/config/apcu.ini /etc/php/7.2/mods-available/apcu.ini

#Memcached
RUN apt-get install -y php-memcached memcached

#apt install php-memcached memcached
COPY ./.docker/config/opcache.ini /etc/php/7.2/mods-available/opcache.ini

#Set ZendOpcache options
COPY ./.docker/config/opcache.ini /etc/php/7.2/mods-available/opcache.ini

#Enable Rewrite Module
RUN a2enmod rewrite

#Enable .htaccess Configuration
COPY ./.docker/config/apache2.conf /etc/apache2/apache2.conf

RUN mkdir -p /var/lib/iHRIS
RUN ln -s /var/lib/iHRIS/lib/4.3.3/ihris-manage/sites/blank/pages /var/www/html/manage

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

EXPOSE 80
VOLUME ["$APACHE_LOG_DIR"]
CMD ["/usr/sbin/apache2ctl", "-DFOREGROUND"]
