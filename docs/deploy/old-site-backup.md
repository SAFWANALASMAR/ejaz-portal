# نسخة كاملة من موقع ووردبريس القديم على hosting.com

> لصفوان، قبل أي تغيير في DNS أو الاستضافة، ومرتبط من [دليل الرفع](hostinger-go-live.md) و[نقل النطاق](hostinger-domain-cutover.md).
>
> - كل ما هنا خطوات ينفذها صفوان أو تُنفذ بإذنه.
> - لم يُدخَل إلى لوحة hosting.com، ولا سر هنا.
> - وأسماء القوائم في اللوحة من cPanel المعتاد. 【ناقص: تأكيد أن لوحة hosting.com هي cPanel】، فإن اختلفت فالمطلوب نفسه بأسمائها.

## ما يوجد اليوم من الموقع القديم

- **أرشيف المحتوى** (سُحب في 10 أكتوبر 2026 إلى جهاز صفوان): مرفق إصدار `legacy-site-archive` في المستودع الخاص.
  - الملف `legacy-site-archive.zip`، 143 ميجابايت، و155 ملفاً: صور، وأيقونات، وPDF، وMP3، وفهارس الصفحات وروابط المرئيات.
  - وبصمته SHA-256 مكتوبة في [`docs/legacy-import/README.md`](../legacy-import/README.md) «الأرشيف الكامل على GitHub»، مع أوامر تنزيله ومطابقته.
- **ملف الاستيراد** [`docs/legacy-import/legacy-site.json`](../legacy-import/legacy-site.json) و`redirects.csv` المبنيان منه، وتقرير النقل [`docs/reviews/2026-10-11-archive-migration-report.md`](../reviews/2026-10-11-archive-migration-report.md).
- **هذا الأرشيف محتوى منقول، لا نسخة من الموقع:** لا قاعدة ووردبريس، ولا `wp-content` كاملاً، ولا الإضافات والقالب. فالنسخة أدناه تلزم قبل أي تغيير (`cutover.md` «ما يلزم قبل يوم النقل»).

## ١. نسخة الحساب الكاملة من اللوحة

1. في لوحة hosting.com: **Backup** أو **Backup Wizard**، ثم **Download a Full Account Backup**.
2. الوجهة «Home Directory»، وبريد للتنبيه عند اكتمالها.
3. تنزيل الملف (`backup-<التاريخ>_<الحساب>.tar.gz`) إلى جهاز صفوان.
- **ما فيها:** ملفات الحساب كلها، وقواعد MySQL، وإعدادات البريد والنطاقات.
- **البريد:** صناديق info@ejaz.sa على مايكروسوفت 365 لا على الاستضافة (`MX` إلى outlook في [صورة DNS](hostinger-domain-cutover.md))، فلا صناديق تُنسخ هنا. وإن وُجدت صناديق قديمة في اللوحة فهي داخل النسخة الكاملة. 【ناقص: هل في الحساب صناديق بريد】

## ٢. القاعدة ومجلد `wp-content` منفصلين

لأن فتح النسخة الكاملة يحتاج cPanel، والمنفصلان يُفتحان في أي مكان:

1. **القاعدة:**
   - phpMyAdmin ← قاعدة الموقع ← **Export** ← «Custom» ← SQL، بالجداول كلها وبضغط gzip.
   - أو من **Backup** ← «Download a MySQL Database Backup».
   - اسم القاعدة في `wp-config.php` (`DB_NAME`). 【ناقص: اسم القاعدة】
2. **الملفات:**
   - مدير الملفات ← `public_html` (أو مجلد الموقع) ← ضغط `wp-content` كاملاً إلى zip ← تنزيل.
   - ومعه `wp-config.php` و`.htaccess` منفصلين. **`wp-config.php` فيه كلمة مرور القاعدة ومفاتيح ووردبريس، فيُعامل سراً:** يُشفَّر مع البقية (الخطوة ٤)، ولا يُرفع إلى قيت ولا يُرسل في محادثة.
3. **إصدار ووردبريس وإضافاته:** من «لوحة ووردبريس ← التحديثات والإضافات»، لقطة أو قائمة، لأن الاستعادة المحلية تحتاج الإصدار نفسه تقريباً. 【ناقص: إصدار ووردبريس】

## ٣. أرشيف ثابت للصفحات (مرجع بصري)

من جهاز صفوان، بإذنه، بعد تأكيد أن الموقع القديم ما زال يعمل. والأرشيف السابق نصوص وملفات لا صفحات كما تُعرض:

```bash
wget --mirror --convert-links --adjust-extension --page-requisites --no-parent \
     --wait=1 --random-wait --domains=ejaz.sa,www.ejaz.sa \
     -e robots=off -P ejaz-sa-static https://ejaz.sa/
```

- `--wait=1 --random-wait` لئلا يُثقل الموقع القديم.
- ومعه لقطات صفحة كاملة لأهم الصفحات على 1440 و390 من المتصفح، مرجعاً بصرياً للمقارنة.
- وقائمة الروابط من الأرشيف تُقارن بـ `redirects.csv`: كل رابط قديم له تحويل (اختبار `ArchiveMigrationTest` يغطي القائمة المعروفة).

## ٤. أين تُحفظ: نسختان

1. **تجميع وبصمة:** المجلد `ejaz-old-site-<التاريخ>/` فيه النسخة الكاملة، والقاعدة، و`wp-content.zip`، و`wp-config.php`، و`.htaccess`، و`ejaz-sa-static/`، واللقطات. ثم `sha256sum * > SHA256SUMS`.
2. **تشفير قبل الإخراج من الجهاز** لأن فيه القاعدة (حسابات ووردبريس وبريدها) و`wp-config.php`. ويُشفَّر بمفتاح GPG العام للنسخ (`scripts/restore.md`)، والخاص خارج الخادم:

   ```bash
   tar -czf ejaz-old-site-<التاريخ>.tar.gz ejaz-old-site-<التاريخ>/
   gpg --encrypt --recipient <معرّف مفتاح النسخ> ejaz-old-site-<التاريخ>.tar.gz
   ```

3. **النسخة الأولى:** جهاز صفوان (المجلد الأصلي والملف المشفّر).
4. **النسخة الثانية خارجه:** الملف المشفّر في حاوية النسخ عند Hetzner. `cutover.md` «بعد النقل» يحفظ «أرشيف الموقع القديم في حاوية النسخ». أو في مكان تملكه الجمعية. 【ناقص: المكان الخارجي إن لم تكن حاوية Hetzner جاهزة قبل النقل】

## ٥. التحقق بفتحها محلياً

1. **البصمات:** `sha256sum -c SHA256SUMS` بلا خطأ، وفك تشفير الملف المشفّر على جهاز آخر بالمفتاح الخاص.
2. **الأرشيف الثابت:** فتح `ejaz-sa-static/ejaz.sa/index.html` في المتصفح، والتنقل بين الصفحات والصور بلا اتصال.
3. **ووردبريس محلياً بحاويتين** على جهاز صفوان، من مجلد مؤقت لا علاقة له بالبوابة:

   ```bash
   docker network create oldsite
   docker run -d --name oldsite-db --network oldsite -e MARIADB_ROOT_PASSWORD=<مؤقتة> -e MARIADB_DATABASE=wp mariadb:11
   gunzip -c <ملف القاعدة>.sql.gz | docker exec -i oldsite-db mariadb -uroot -p<مؤقتة> wp
   unzip wp-content.zip -d site/
   docker run -d --name oldsite-wp --network oldsite -p 127.0.0.1:8090:80 \
     -e WORDPRESS_DB_HOST=oldsite-db -e WORDPRESS_DB_USER=root -e WORDPRESS_DB_PASSWORD=<مؤقتة> -e WORDPRESS_DB_NAME=wp \
     -e WORDPRESS_TABLE_PREFIX=<البادئة من wp-config.php> \
     -e WORDPRESS_CONFIG_EXTRA="define('WP_HOME','http://localhost:8090'); define('WP_SITEURL','http://localhost:8090');" \
     -v "$PWD/site/wp-content:/var/www/html/wp-content" wordpress:<الإصدار القريب>
   ```

   - ثم `http://localhost:8090`: الصفحات والصور تظهر. والمنفذ مربوط بـ `127.0.0.1` وحده، فلا يصله جهاز آخر في الشبكة.
   - **وتُحذف الحاويتان بعد التحقق:** `docker rm -f oldsite-wp oldsite-db && docker network rm oldsite`. وإصدار الصورة 【ناقص: من الخطوة ٢.٣】.
4. **النتيجة تُكتب** في `docs/backups/<التاريخ>-old-site.md`: ما فُتح، والبصمات، ومكانا النسختين.
