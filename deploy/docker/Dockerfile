# syntax=docker/dockerfile:1
# صورة الإنتاج — بوابة جمعية إعجاز (docs/deploy.md). تُبنى من جذر المشروع:
#   docker compose --env-file .env -f deploy/compose.production.yaml build
# الشيفرة داخل الصورة، والأسرار من البيئة وقت التشغيل لا وقت البناء.

# ---- الأساس: PHP-FPM 8.4 بإضافات البوابة ----
# رقعة مثبّتة لا وسم متحرك: 8.4.27 فأحدث (إصدار 21 أكتوبر 2026 الأمني، docs/security.md «تحديث PHP وnginx»). وكل رقعة
# أمنية جديدة تُرفع هنا وفي docker/php/Dockerfile، ثم build --pull
FROM php:8.4.27-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql pgsql redis intl gd zip bcmath pcntl exif opcache sockets \
    && apt-get update \
    && apt-get install -y --no-install-recommends ffmpeg libfcgi-bin unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# ---- مكتبات PHP بلا أدوات التطوير ----
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --no-progress

# ---- الواجهة: Vite (يحتاج قوالب الترقيم من vendor لـ Tailwind) ----
FROM node:22-bookworm-slim AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
RUN npm run build

# ---- التطبيق: PHP-FPM، ومنه العمال والمجدول ----
FROM base AS app
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /var/www/html/public/build ./public/build
COPY deploy/php/production.ini /usr/local/etc/php/conf.d/99-ejaz.ini
COPY deploy/docker/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-ejaz.conf
COPY deploy/docker/entrypoint.sh /usr/local/bin/ejaz-entrypoint

RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/ejaz-entrypoint \
    && rm /usr/bin/composer

# القوالب المترجمة داخل الحاوية لا في المجلد المشترك، فلا تتسابق عليها الحاويات عند النشر
ENV VIEW_COMPILED_PATH=/tmp/ejaz-views \
    FPM_MAX_CHILDREN=40

USER www-data
ENTRYPOINT ["ejaz-entrypoint"]
CMD ["php-fpm", "--nodaemonize"]

# ---- خادم الويب: nginx بالملفات العامة المبنية ----
# الفرع المستقر الذي يتلقى الرقع (1.30، و1.27 منتهٍ بلا رقع — docs/security.md «تحديث PHP وnginx»)، ويُبنى بـ --pull
FROM nginx:1.30-alpine AS web
# قالب يُكمَل بـ SITE_HOST عند بدء الحاوية (envsubst في صورة nginx الرسمية)
COPY deploy/docker/nginx.conf /etc/nginx/templates/default.conf.template
COPY --from=app /var/www/html/public /var/www/html/public

# ---- النسخ الاحتياطي: pg_dump بإصدار القاعدة نفسه (17)، وgpg، وaws، وrclone ----
FROM postgres:17-bookworm AS backup
# rclone بإصدار مثبت وبصمته من SHA256SUMS في downloads.rclone.org، لا من مستودع التوزيعة (decisions.md — 19 أكتوبر 2026)
ARG RCLONE_VERSION=v1.75.2
ARG RCLONE_SHA256_AMD64=349ac8fba6ff65d6247043f1750cdcb518ec5d500ef91463a10d37c0ccdf3702
ARG RCLONE_SHA256_ARM64=7e1e8d69654941b7b7df84ee74c5f7fb09cce7d5496947fb7bf16b55ff427d10
RUN apt-get update \
    && apt-get install -y --no-install-recommends gnupg awscli ca-certificates curl unzip \
    && arch="$(dpkg --print-architecture)" \
    && case "$arch" in amd64) sum="$RCLONE_SHA256_AMD64" ;; arm64) sum="$RCLONE_SHA256_ARM64" ;; *) echo "unsupported $arch" >&2; exit 1 ;; esac \
    && curl -fsSLo /tmp/rclone.zip "https://downloads.rclone.org/${RCLONE_VERSION}/rclone-${RCLONE_VERSION}-linux-${arch}.zip" \
    && echo "$sum  /tmp/rclone.zip" | sha256sum -c - \
    && unzip -j -q /tmp/rclone.zip "*/rclone" -d /usr/local/bin \
    && chmod 755 /usr/local/bin/rclone \
    && rm /tmp/rclone.zip \
    && apt-get purge -y curl unzip && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*
COPY scripts/backup.sh /usr/local/bin/ejaz-backup
ENV APP_ROOT=/app
ENTRYPOINT ["bash", "/usr/local/bin/ejaz-backup"]
