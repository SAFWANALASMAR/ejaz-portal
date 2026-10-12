#!/bin/sh
# نقطة دخول حاويات التطبيق والعمال والمجدول: ذاكرة الإعدادات والمسارات والقوالب والأحداث من بيئة التشغيل،
# ثم الأمر المطلوب. ولا cache:clear ولا optimize:clear (docs/security.md).
set -e

case "${APP_ENV:-}" in
  production|staging)
    php artisan config:cache --no-interaction >/dev/null
    php artisan route:cache --no-interaction >/dev/null
    php artisan view:cache --no-interaction >/dev/null
    php artisan event:cache --no-interaction >/dev/null
    php artisan filament:optimize --no-interaction >/dev/null
    ;;
esac

exec "$@"
