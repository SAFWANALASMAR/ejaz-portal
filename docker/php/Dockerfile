# صورة PHP لبيئة التطوير المحلية — بوابة جمعية إعجاز
# رقعة مثبّتة كصورة الإنتاج (deploy/docker/Dockerfile، docs/security.md «تحديث PHP وnginx»)
FROM php:8.4.27-cli-bookworm

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_pgsql pgsql redis intl gd zip bcmath pcntl exif opcache sockets \
    && apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client ffmpeg \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/99-ejaz.ini

# مستخدم بنفس رقم مستخدم الجهاز حتى لا تتعارض صلاحيات الملفات
RUN groupadd -g "${GID}" app 2>/dev/null || true \
    && useradd -o -m -u "${UID}" -g "${GID}" -s /bin/bash app

USER app
RUN git config --global --add safe.directory /var/www/html
WORKDIR /var/www/html

EXPOSE 8000
