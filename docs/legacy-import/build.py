"""Build legacy-site.json and redirects.csv from the local archive of the current site (the only source).
Texts are read verbatim from the archive (02-الصفحات, 06, 08, 01-البيانات); only structure is set here
(slugs, categories, where each image is placed, which images are decorative). Nothing is fetched from ejaz.sa.
Usage (from this folder):
    python -I build.py "C:/Users/safwan/Downloads/أرشيف-موقع-جمعية-إعجاز/أرشيف-موقع-جمعية-إعجاز"
Then copy the files with copy_from_archive.py (same argument)."""
import csv
import json
import os
import re
import sys
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')
ARCHIVE = sys.argv[1]
HERE = os.path.dirname(os.path.abspath(__file__))


def missing(what):
    return f'【ناقص: {what}】'


def read(*parts):
    return open(os.path.join(ARCHIVE, *parts), encoding='utf-8').read()


def data(name):
    return json.loads(read('01-البيانات', name))


def path_of(url):
    """Decoded path of an old URL, as the redirect table stores it."""
    return urllib.parse.unquote(urllib.parse.urlsplit(url).path) or '/'


def page_lines(name):
    body = read('02-الصفحات', name).split('\n---\n', 1)[1]
    return [line.rstrip() for line in body.split('\n')]


def after(lines, marker, start=0):
    """Index of the first non-empty line after `marker`."""
    i = lines.index(marker, start) + 1
    while not lines[i].strip():
        i += 1
    return i


def paragraphs(lines, i):
    """Verbatim paragraphs from line i up to the next heading; blank lines split paragraphs."""
    out, current = [], []
    while i < len(lines) and not lines[i].startswith('#'):
        line = lines[i].strip()
        if line:
            current.append(line)
        elif current:
            out.append(' '.join(current))
            current = []
        i += 1
    if current:
        out.append(' '.join(current))
    return out


def html(paras):
    return ''.join(f'<p>{p}</p>' for p in paras)


def image_in(line):
    found = re.search(r'\(\.\./05-الصور/([^)]+)\)', line)
    return found.group(1) if found else None


# ---------------------------------------------------------------- media (05-الصور: 57 images + 18 SVG icons)
index = data('فهرس-الملفات.json')
ARABIC = re.compile('[\u0600-\u06ff]')
SPECIAL_SLUGS = {
    'cropped-جمعية-إعجاز.png': 'site-icon-png',
    'جمعية-إعجاز.png': 'ejaz-logo-png',
    'شعار-الجمعية.jpeg': 'ejaz-emblem-jpeg',
    'cropped-شعار-الجمعية.jpeg': 'ejaz-emblem-cropped-jpeg',
}
# Icons and ornaments: alt="" (Safwan's decision). Content images get the missing-alt marker.
DECORATIVE = {
    'cultural-1.png', 'occupational-therapy.png', 'civic.png', 'education-technology.png', 'quality-control.png',
    'cultural.png', 'img-2.png', 'Image-901.png', 'Group-61552.png', '1.png', 'world-map1x.png', 'cropped-جمعية-إعجاز.png',
}
# Where each placed image lives in the portal (its old URL redirects there). The rest stay in the library.
PLACED = {
    '2.webp': '/',
    'cropped-جمعية-إعجاز.png': '/',
    'cultural-1.png': '/about', 'occupational-therapy.png': '/about', 'civic.png': '/about',
    'education-technology.png': '/about', 'quality-control.png': '/about', 'img-2.png': '/about',
    'Image-901.png': '/services/assembly-membership', 'Group-61552.png': '/services/assembly-membership',
}

media, media_slug = [], {}
for entry in index:
    rel = entry['الملف']
    if not rel.startswith('05-الصور/'):
        continue
    name = rel.rsplit('/', 1)[1]
    icon = rel.endswith('.svg')
    slug = SPECIAL_SLUGS.get(name) or ('icon-' if icon else '') + re.sub('[^a-z0-9]+', '-', name.lower()).strip('-')
    assert len(slug) <= 80 and slug not in media_slug.values(), slug
    media_slug[name] = slug
    decorative = icon or name in DECORATIVE
    media.append({
        'slug': slug,
        'file': ('files/icons/' if icon else 'files/images/') + name,
        'title': entry['العنوان'] or name,
        'alt': None if decorative else missing('نص بديل'),
        'is_decorative': decorative,
        'from_template': not any(ARABIC.search(p) for p in entry['مستخدم_في']),
        'target': PLACED.get(name, '/'),
        'legacy_paths': [path_of(entry['الرابط_الأصلي'])],
    })

# ---------------------------------------------------------------- pages
home = page_lines('01 - الصفحة الرئيسية.md')
hero_heading = '## جمعية إعجاز للدراسات والبحوث العلمية'
hero_i = after(home, hero_heading)
hero_text = paragraphs(home, hero_i)[0]
hero_button = home[after(home, home[hero_i], hero_i)].strip()
assert hero_button == 'تعرف على الجمعية', hero_button

about_paras = paragraphs(home, after(home, '## ✦ من نحن'))
mission = paragraphs(home, after(home, '## الرســــالة'))
vision = paragraphs(home, after(home, '## الرؤيــــة'))
goals_i = home.index('## الاهداف الاستراتيجية لجمعية إعجاز للدراسات والبحوث العلمية')
goals_end = home.index('###### ✦ مبادرات')
goal_titles = [line[5:] for line in home[goals_i:goals_end] if line.startswith('#### ')]
goal_images = [image_in(line) for line in home[goals_i:goals_end] if image_in(line)]
values_i = home.index('## قيم جمعية "إعجاز" للدراسات والبحوث العلمية')
values_end = home.index('## Business Insight and strategic vision..')
values = [{'icon': None, 'title': home[i][6:], 'text': paragraphs(home, after(home, home[i], i))[0]}
          for i in range(values_i, values_end) if home[i].startswith('##### ')]
values_image = [image_in(line) for line in home[values_i:values_end] if image_in(line)]
assert len(goal_titles) == 5 and len(goal_images) == 5 and len(values) == 4 and values_image == ['img-2.png']

about = {
    'slug': 'about',
    'title': 'عن الجمعية',
    # The first verbatim paragraph is the page description for search engines (max 300 characters)
    'summary': about_paras[0] if len(about_paras[0]) <= 300 else None,
    'blocks': [
        {'type': 'text', 'data': {'heading': 'من نحن', 'body': html(about_paras)}},
        {'type': 'text', 'data': {'heading': 'الرســــالة', 'body': html(mission)}},
        {'type': 'text', 'data': {'heading': 'الرؤيــــة', 'body': html(vision)}},
        # In the old menu only, with no link and no text
        {'type': 'text', 'data': {'heading': 'التوجه الاستراتيجي', 'body': html([missing('محتوى التوجه الاستراتيجي')])}},
        {'type': 'cards', 'data': {'heading': 'الأهداف الاستراتيجية', 'items': [
            {'icon': None, 'title': t, 'text': None, 'media': media_slug[img]} for t, img in zip(goal_titles, goal_images)]}},
        {'type': 'image', 'data': {'media': media_slug[values_image[0]]}},
        {'type': 'cards', 'data': {'heading': 'قيمنا', 'items': values}},
    ],
}

contact = page_lines('02 - تواصل معنا.md')
contact_text = paragraphs(contact, after(contact, '## تواصل معنا'))[0]
email = [line[7:] for line in contact if line.startswith('###### ') and '@' in line][0]
address = [line[7:] for line in contact if line.startswith('###### شارع')][0]
map_url = re.search(r'— (https://maps\.app\.goo\.gl/\S+)', read('03-القوائم-والتنقل', 'القائمة-الرئيسية.md')).group(1)
assert email == 'info@ejaz.sa'

join = page_lines('04 - طلب الانضمام للجمعية العمومية.md')
join_text = join[after(join, "## Let's talk.")].strip()
join_images = [image_in(line) for line in join if image_in(line)]
assert join_text.startswith('لطلب الانضمام') and join_images == ['Image-901.png', 'Group-61552.png']

empty_text = [{'type': 'text', 'data': {'heading': None, 'body': html([missing('نص الصفحة')])}}]
pages = [
    about,
    {'slug': 'contact', 'title': 'تواصل معنا', 'summary': contact_text,
     'blocks': [{'type': 'text', 'data': {'heading': None, 'body': html([contact_text])}}]},
    {'slug': 'inquiries', 'title': 'الاستفسارات والاقتراحات', 'blocks': empty_text},
    {'slug': 'assembly-membership', 'title': 'طلب الانضمام للجمعية العمومية', 'summary': join_text,
     'blocks': [
         {'type': 'image', 'data': {'media': media_slug[join_images[0]]}},
         {'type': 'image', 'data': {'media': media_slug[join_images[1]]}},
         # The old page wrote info@ejaz.org by mistake; Safwan's decision: info@ejaz.sa
         {'type': 'text', 'data': {'heading': 'طلب الانضمام للجمعية العمومية',
                                   'body': f'<p>{join_text}<br><strong><a href="mailto:{email}">{email}</a></strong></p>'}},
     ]},
    {'slug': 'jobs', 'title': 'التوظيف', 'blocks': empty_text},
    {'slug': 'volunteering', 'title': 'التطوع', 'blocks': empty_text},
    {'slug': 'reports', 'title': 'التقارير', 'blocks': empty_text},
]

# ---------------------------------------------------------------- initiatives (short names as in overview.md)
INITIATIVES = [('qiyam', 'قيم', '08 - مبادرة قيـم.md'), ('tatweer', 'تطوير', '09 - مبادرة تطوير.md'),
               ('kunooz', 'كنوز', '10 - مبادرة كنوز.md'), ('ibtikar', 'ابتكار', '11 - مبادرة ابتكــــار.md'),
               ('irtiqa', 'ارتقاء', '12 - مبادرة ارتقاء.md')]
initiatives = []
for slug, name, file in INITIATIVES:
    lines = page_lines(file)
    heading = [line for line in lines if line.startswith('## ')][0]
    original = read('02-الصفحات', file).split('الرابط-الأصلي: ', 1)[1].split('\n', 1)[0]
    initiatives.append({'slug': slug, 'name': name, 'summary': paragraphs(lines, after(lines, heading))[0],
                        'legacy_paths': [path_of(original)]})

# ---------------------------------------------------------------- documents (titles = the Arabic file names)
DOCUMENTS = [
    ('license-certificate', 'official', 'شهادة-ترخيص-الجمعية.pdf'),
    ('registration-decision', 'official', 'قرار-تسجيل-الجمعية.pdf'),
    ('board-formation-decision', 'official', 'قرار-تشكيل-مجلس-الإدارة.pdf'),
    ('no-branches-statement', 'official', 'افادة-بعدم-وجود-فروع-أو-مكاتب-للجمعية.pdf'),
    ('data-privacy-policy', 'policies', 'سياسة-خصوصية-البيانات.pdf'),
    ('fundraising-policy', 'policies', 'سياسة-جمع-التبرعات.pdf'),
    ('conflict-of-interest-policy', 'policies', 'سياسة-تعارض-المصالح.pdf'),
    ('document-retention-policy', 'policies', 'سياسة-الاحتفاظ-بالوثائق-وإتلافها.pdf'),
    ('whistleblowing-policy', 'policies', 'سياسة-الإبلاغ-عن-المخالفات-وحماية-مقدمي-البلاغات.pdf'),
    ('donation-redirection-policy', 'policies', 'سياسة-إعادة-توجيه-مبلغ-التبرع-لمشروع-آخر.pdf'),
    ('volunteer-management-policy', 'policies', 'سياسة-إدارة-المتطوعين.pdf'),
    ('beneficiary-eligibility-mechanism', 'procedures', 'آلية-التحقق-من-استحقاق-المستفيد-للخدمة.pdf'),
    ('donation-refund-mechanism', 'procedures', 'آلية-استرداد-التبرع-للمتبرع.pdf'),
    ('disclosure-document', 'official', 'وثيقة-الافصاح.pdf'),
]
url_of = {e['الملف'].rsplit('/', 1)[1]: e['الرابط_الأصلي'] for e in index}
assert sorted(f for _, _, f in DOCUMENTS) == sorted(os.listdir(os.path.join(ARCHIVE, '04-الوثائق-والسياسات')))
documents = [{'slug': slug, 'title': file[:-4].replace('-', ' '), 'category': category, 'issued_at': None, 'version': None,
              'file': 'files/documents/' + file, 'legacy_paths': [path_of(url_of[file])]} for slug, category, file in DOCUMENTS]

# ---------------------------------------------------------------- library: 30 audio, 20 videos
audio_md = read('06-المكتبة-الصوتية', 'فهرس-الصوتيات.md')
audio = []
for number, title, url in re.findall(r'^(\d+)\. (.+)\n   - الملف: .+\n   - الرابط الأصلي: (\S+)$', audio_md, re.M):
    if title.startswith('[ناقص'):
        title = missing('عنوان')
    audio.append({'slug': f'audio-{int(number):02d}', 'section': 'audio', 'title': title,
                  'file': 'files/audio/' + url.rsplit('/', 1)[1], 'legacy_paths': [path_of(url)]})
videos_md = read('08-المكتبة-المرئية', 'قائمة-المرئيات.md')
videos = [{'slug': f'video-{int(n):02d}', 'section': 'videos', 'title': t, 'video_url': u}
          for n, t, u in re.findall(r'^(\d+)\. (.+)\n   - الرابط: (\S+)$', videos_md, re.M)]
home_video = re.search(r'## مقطع مضمّن في الصفحة الرئيسية\s+- (\S+)', videos_md).group(1)
assert len(audio) == 30 and len(videos) == 20

# ---------------------------------------------------------------- settings
settings = {
    'home': {'hero_title': hero_heading[3:], 'hero_text': hero_text, 'hero_button_label': hero_button,
             'hero_button_url': '/about', 'hero_media': media_slug['2.webp'], 'video_url': home_video},
    'contact': {'email': email, 'address': address, 'map_url': map_url},
}

# ---------------------------------------------------------------- redirects
news = [path_of(n['link']) for n in data('الأخبار.json')]  # template demo posts, not migrated
redirects = [{'from': p, 'to': '/media/news'} for p in news]

out = {'media': media, 'pages': pages, 'initiatives': initiatives, 'documents': documents,
       'library': audio + videos, 'settings': settings, 'redirects': redirects}
with open(os.path.join(HERE, 'legacy-site.json'), 'w', encoding='utf-8', newline='\n') as f:
    json.dump(out, f, ensure_ascii=False, indent=2)
    f.write('\n')

# redirects.csv: every old URL in the archive and its final destination in the portal.
# kind: record (a record's old path), media, seeded (LegacyRedirectsSeeder), news (template posts),
# served (a portal route itself), pattern (LegacyPaths: WordPress paths with no registered redirect)
SEEDED = {
    '/التقارير/': '/governance?category=annual-reports', '/ejaz_library': '/library', '/video_library/': '/library/videos',
    '/audio_library/': '/library/audio', '/books-and-publications': '/library/books', '/research-and-studies': '/library/research',
    '/الاستفسارات-والاقتراحات/': '/contact', '/طلب-الانضمام-للجمعية-العمومية/': '/services/assembly-membership',
    '/التطوع/': '/services/volunteering', '/التوظيف/': '/services/jobs',
}
rows = []
rows += [(d['legacy_paths'][0], '/governance/' + d['slug'], 'record') for d in documents]
rows += [(a['legacy_paths'][0], '/library/audio/' + a['slug'], 'record') for a in audio]
rows += [(i['legacy_paths'][0], '/initiatives/' + i['slug'], 'record') for i in initiatives]
rows += [(m['legacy_paths'][0], m['target'], 'media') for m in media]
rows += [(source, target, 'seeded') for source, target in SEEDED.items()]
rows += [(p, '/media/news', 'news') for p in news]
rows += [('/', '/', 'served'), ('/contact/', '/contact', 'served')]
# Files that are not migrated (the missing image, a script, a stylesheet): nearest section
rows += [(path_of(e['الرابط_الأصلي']), '/', 'pattern') for e in index if not e['الملف'].startswith(('04-', '05-', '06-'))]
rows += [(source, target, 'pattern') for source, target in [
    ('/?p=10420', '/media/news'), ('/?page_id=17', '/'), ('/category/uncategorized/', '/media/news'),
    ('/feed/', '/media/news'), ('/2021/10/07/hello-world/', '/media/news'),
    ('/wp-content/uploads/2021/10/not-in-archive.jpg', '/'), ('/wp-content/uploads/2026/01/not-in-archive.pdf', '/governance'),
    ('/wp-content/uploads/2026/08/not-in-archive.mp3', '/library/audio')]]

old_urls = {path_of(e['الرابط_الأصلي']) for e in index} | {path_of(p['link']) for p in data('الصفحات.json')}
old_urls |= {path_of(c['url']) for item in data('القائمة.json')['الترويسة'] for c in [item, *item['children']] if '#' not in c['url']}
assert old_urls <= {r[0] for r in rows}, old_urls - {r[0] for r in rows}
assert len({r[0] for r in rows}) == len(rows), 'duplicate source'
with open(os.path.join(HERE, 'redirects.csv'), 'w', encoding='utf-8', newline='') as f:
    writer = csv.writer(f, lineterminator='\n')
    writer.writerow(['source', 'target', 'kind'])
    writer.writerows(rows)

print(f'media {len(media)} (icons {sum(m["file"].startswith("files/icons/") for m in media)}), pages {len(pages)}, '
      f'initiatives {len(initiatives)}, documents {len(documents)}, audio {len(audio)}, videos {len(videos)}, '
      f'news redirects {len(redirects)}, csv rows {len(rows)}')
