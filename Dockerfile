# Render.com — Amer AI server (PHP 8.2 + Apache, zero code changes)
# NOTE: curl + mbstring + opcache are preinstalled in this image — no apt needed.
FROM php:8.2-apache

# copy the whole site (frontend + amer/ backend)
COPY . /var/www/html/

# writable runtime dirs for the agent (conversations/usage/logs)
RUN mkdir -p /var/www/html/amer/database/logs \
  && chown -R www-data:www-data /var/www/html/amer/database \
  && chmod -R 750 /var/www/html/amer/database

# Apache: allow .htaccess overrides (protects database/logs)
RUN a2enmod rewrite headers && \
    sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

EXPOSE 80
