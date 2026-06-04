FROM php:8.2-apache

# 1. Installations de base (GD, PDO MySQL)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    msmtp msmtp-mta gettext-base \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# 2. Configuration Apache (Rewrite et DocumentRoot)
RUN a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf

# 3. Configuration de PHP pour utiliser msmtp
COPY custom.ini /usr/local/etc/php/conf.d/custom.ini

# 4. Préparation du template msmtp
COPY msmtprc.template /etc/msmtprc.template

# 5. COMMANDE DE LANCEMENT (TOUJOURS À LA FIN)
# Cette commande génère le fichier de mail ET lance Apache
CMD envsubst < /etc/msmtprc.template > /etc/msmtprc && \
    chmod 600 /etc/msmtprc && \
    chown www-data:www-data /etc/msmtprc && \
    apache2-foreground
