
FROM php:8.3-apache



COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html



RUN apt-get update && apt-get install -y \
    libpq-dev curl git unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configurar Apache — pasar Authorization header a PHP (crítico para JWT)
RUN echo '<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
    CGIPassAuth On\n\
    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1\n\
</Directory>' > /etc/apache2/conf-available/petspa.conf \
    && a2enconf petspa

COPY . /var/www/html/

# Instalar PHPMailer via composer
RUN cd /var/www/html && composer require phpmailer/phpmailer --no-interaction 2>/dev/null || true

RUN mkdir -p /var/www/html/uploads/fotos \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/uploads

ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/sites-available/default-ssl.conf

EXPOSE 80