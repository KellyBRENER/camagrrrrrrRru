FROM php:8.2-apache

# 1. Installations de base (GD, PDO MySQL)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    msmtp msmtp-mta gettext-base openssl \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd

# 2. Configuration Apache (Rewrite et DocumentRoot)
RUN a2enmod rewrite ssl
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
RUN openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout /etc/ssl/private/camagru-selfsigned.key \
    -out /etc/ssl/certs/camagru-selfsigned.crt \
    -subj "/C=FR/ST=Dev/L=Local/O=Camagru/CN=localhost" \
    -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"
COPY default-ssl.conf /etc/apache2/sites-available/default-ssl.conf
RUN a2ensite default-ssl

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
