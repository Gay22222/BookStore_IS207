# PHP + Apache (1 container, đơn giản để deploy)
FROM php:8.2-apache

# ------------------------------------------------------------
# Enable Apache modules + install system libraries
# ------------------------------------------------------------
RUN a2enmod rewrite headers \
 && apt-get update \
 && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libssl-dev \
# ------------------------------------------------------------
# QUANG ADD
# Cài PHP Redis extension (phpredis)
# BẮT BUỘC khi dùng:
#   REDIS_CLIENT=phpredis
#   CACHE_STORE=redis
# ------------------------------------------------------------
 && pecl install redis \
 && docker-php-ext-enable redis \
# ------------------------------------------------------------
# PHP extensions cho Laravel
# ------------------------------------------------------------
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip bcmath

# ------------------------------------------------------------
# Apache DocumentRoot -> public/
# 
# ------------------------------------------------------------
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
 && sed -ri -e 's!<Directory /var/www/>!<Directory ${APACHE_DOCUMENT_ROOT}>!g' /etc/apache2/apache2.conf \
 && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# ------------------------------------------------------------
# Working directory
# ------------------------------------------------------------
WORKDIR /var/www/html

# ------------------------------------------------------------
# Copy source code
# ------------------------------------------------------------
COPY . .

# ------------------------------------------------------------
# Install Composer 
# ------------------------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress

# ------------------------------------------------------------
# Fix permissions cho Laravel runtime
# (storage + bootstrap/cache)
# ------------------------------------------------------------
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# ------------------------------------------------------------
# Runtime PORT
# - Local: 8080
# - Cloud (Koyeb): override bằng $PORT
# ------------------------------------------------------------
ENV PORT=8080

# ------------------------------------------------------------
# Entrypoint
# (script đã được fix LF + shebang)
# ------------------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ------------------------------------------------------------
# Expose PORT (chỉ mang tính documentation)
# ------------------------------------------------------------
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
