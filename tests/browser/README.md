# اختبارات المتصفح ولقطات الشاشات

تتحقق من كل شاشة على ثلاثة عروض (1440 و820 و390): الاتجاه من اليمين، والعناصر الأساسية، وعدم وجود تمرير أفقي. وتحفظ لقطة كاملة لكل شاشة في `docs/design/screenshots/<الشاشة>-<العرض>.png` للمقارنة بصور التصميم (`docs/design/ui-reference.md`).

تعمل في حاوية Playwright الرسمية على شبكة Docker، فلا يُثبَّت متصفح على الجهاز.

## التشغيل

البيئة تعمل (`docker compose up -d`)، ثم:

```bash
# مستخدم تجريبي بكلمة مرور عشوائية لهذا التشغيل (البيئة المحلية فقط)
creds=$(docker compose exec -T app php artisan ejaz:demo-user --json)

docker compose run --rm \
  -e DEMO_EMAIL="$(echo "$creds" | sed -E 's/.*"email":"([^"]+)".*/\1/')" \
  -e DEMO_PASSWORD="$(echo "$creds" | sed -E 's/.*"password":"([^"]+)".*/\1/')" \
  browser
```

بدون المستخدم التجريبي تُختبر صفحات الزائر فقط.

## إضافة شاشة

أضفها إلى `guestScreens` أو `accountScreens` في `screens.spec.mjs`: الاسم، والمسار، ومحدد العنوان الرئيسي.
