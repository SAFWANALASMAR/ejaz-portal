"""Build legacy-site.json for `php artisan import:legacy-site` from the pages fetched from ejaz.sa.
Texts are taken verbatim from the extracted page text (home.txt, text.txt, audio_map.txt); nothing is typed by hand
except structure (slugs, categories, headings that the site shows). Files are referenced relative to this folder."""
import json
import os
import sys

sys.stdout.reconfigure(encoding='utf-8')
home = [l.rstrip('\n') for l in open('home.txt', encoding='utf-8')]
pages_txt = open('text.txt', encoding='utf-8').read()


def after(lines, marker, count, start=0):
    i = lines.index(marker, start)
    return lines[i + 1:i + 1 + count], i


# ---- about page (from the home page sections of the current site)
about_paras, i_about = after(home, '✦ من نحن', 2)
mission, _ = after(home, 'الرســــالة', 1)
vision, _ = after(home, 'الرؤيــــة', 1)
goals, _ = after(home, 'الاهداف الاستراتيجية لجمعية إعجاز للدراسات والبحوث العلمية', 5)
v_i = home.index('قيم جمعية "إعجاز" للدراسات والبحوث العلمية')
values = []
k = v_i + 1
while len(values) < 4:
    # number line, title line, description line
    num, title, text = home[k], home[k + 1], home[k + 2]
    assert num.strip().isdigit(), (num, title)
    values.append({'icon': None, 'title': title, 'text': text})
    k += 3

about = {
    'slug': 'about',
    'title': 'عن الجمعية',
    'blocks': [
        {'type': 'text', 'data': {'heading': 'من نحن', 'body': ''.join(f'<p>{p}</p>' for p in about_paras)}},
        {'type': 'text', 'data': {'heading': 'الرســــالة', 'body': f'<p>{mission[0]}</p>'}},
        {'type': 'text', 'data': {'heading': 'الرؤيــــة', 'body': f'<p>{vision[0]}</p>'}},
        {'type': 'cards', 'data': {'heading': 'الأهداف الاستراتيجية', 'items': [{'icon': None, 'title': g, 'text': None} for g in goals]}},
        {'type': 'cards', 'data': {'heading': 'قيمنا', 'items': values}},
    ],
}

# ---- initiatives (each page's description, verbatim)
inits = []
for slug, legacy in [('qiyam', '/مبادرة-قيـم/'), ('tatweer', '/مبادرة-تطوير/'), ('kunooz', '/مبادرة-كنوز/'),
                     ('ibtikar', '/مبادرة-ابتكــــار/'), ('irtiqa', '/مبادرة-ارتقاء/')]:
    block = pages_txt.split(f'=== {slug}\n', 1)[1].split('\n===', 1)[0].strip().split('\n')
    name = block[0].replace('✦', '').strip().replace('مبادرة', '', 1).strip()
    inits.append({'slug': slug, 'name': {'qiyam': 'قيم', 'tatweer': 'تطوير', 'kunooz': 'كنوز', 'ibtikar': 'ابتكار', 'irtiqa': 'ارتقاء'}[slug],
                  'summary': block[1], 'legacy_paths': [legacy]})
    print('initiative', slug, '|', name, '|', block[1][:40])

# ---- documents (14), titles from the file names on the current site
categories = {
    'شهادة-ترخيص-الجمعية': ('license-certificate', 'official'),
    'قرار-تسجيل-الجمعية': ('registration-decision', 'official'),
    'قرار-تشكيل-مجلس-الإدارة': ('board-formation-decision', 'official'),
    'افادة-بعدم-وجود-فروع-أو-مكاتب-للجمعية': ('no-branches-statement', 'official'),
    'سياسة-خصوصية-البيانات': ('data-privacy-policy', 'policies'),
    'سياسة-جمع-التبرعات': ('fundraising-policy', 'policies'),
    'سياسة-تعارض-المصالح': ('conflict-of-interest-policy', 'policies'),
    'سياسة-الاحتفاظ-بالوثائق-وإتلافها': ('document-retention-policy', 'policies'),
    'سياسة-الإبلاغ-عن-المخالفات-وحماية-مقدمي-البلاغات': ('whistleblowing-policy', 'policies'),
    'سياسة-إعادة-توجيه-مبلغ-التبرع-لمشروع-آخر': ('donation-redirection-policy', 'policies'),
    'سياسة-إدارة-المتطوعين': ('volunteer-management-policy', 'policies'),
    'آلية-التحقق-من-استحقاق-المستفيد-للخدمة': ('beneficiary-eligibility-mechanism', 'procedures'),
    'آلية-استرداد-التبرع-للمتبرع': ('donation-refund-mechanism', 'procedures'),
    'وثيقة-الافصاح': ('disclosure-document', 'official'),
}
# the titles as the current site writes them (its governance links)
titles = {
    'شهادة-ترخيص-الجمعية': 'شهادة ترخيص الجمعية', 'قرار-تسجيل-الجمعية': 'قرار تسجيل الجمعية',
    'قرار-تشكيل-مجلس-الإدارة': 'قرار تشكيل مجلس الإدارة', 'افادة-بعدم-وجود-فروع-أو-مكاتب-للجمعية': 'إفادة بعدم وجود فروع أو مكاتب للجمعية',
    'سياسة-خصوصية-البيانات': 'سياسة خصوصية البيانات', 'سياسة-جمع-التبرعات': 'سياسة جمع التبرعات',
    'سياسة-تعارض-المصالح': 'سياسة تعارض المصالح', 'سياسة-الاحتفاظ-بالوثائق-وإتلافها': 'سياسة الاحتفاظ بالوثائق وإتلافها',
    'سياسة-الإبلاغ-عن-المخالفات-وحماية-مقدمي-البلاغات': 'سياسة الإبلاغ عن المخالفات وحماية مقدمي البلاغات',
    'سياسة-إعادة-توجيه-مبلغ-التبرع-لمشروع-آخر': 'سياسة إعادة توجيه مبلغ التبرع لمشروع آخر',
    'سياسة-إدارة-المتطوعين': 'سياسة إدارة المتطوعين', 'آلية-التحقق-من-استحقاق-المستفيد-للخدمة': 'آلية التحقق من استحقاق المستفيد للخدمة',
    'آلية-استرداد-التبرع-للمتبرع': 'آلية استرداد التبرع للمتبرع', 'وثيقة-الافصاح': 'وثيقة الإفصاح',
}
docs = []
for stem, (slug, cat) in categories.items():
    path = f'files/documents/{stem}.pdf'
    assert os.path.exists(path), path
    docs.append({'slug': slug, 'title': titles[stem], 'category': cat, 'issued_at': None, 'version': None,
                 'file': path, 'legacy_paths': [f'/wp-content/uploads/2026/01/{stem}.pdf']})

# ---- audio library: title → file in page order (two files for the first title), one title without file
audio = []
for line in open('audio_map.txt', encoding='utf-8'):
    if not line.startswith('{'):
        continue
    row = json.loads(line)
    n = int(row['file'].rsplit('E3jaz-', 1)[1].split('.mp3')[0])
    title = row['title']
    if title.startswith('http'):  # second player under the same title
        title = audio[-1]['title']
    path = f'files/audio/E3jaz-{n}.mp3'
    assert os.path.exists(path), path
    audio.append({'slug': f'audio-{n:02d}', 'section': 'audio', 'title': title, 'file': path,
                  'legacy_paths': [f'/wp-content/uploads/2026/08/E3jaz-{n}.mp3']})
audio.append({'slug': 'audio-oceans', 'section': 'audio', 'title': 'أعماق المحيطات', 'file': None})

# ---- video library: 20 titles, no link on the current site
video_titles = [
    'الناصية', 'تداعى الجسد بالسهر والحمى', 'مراحل خلق الجنين في القرآن الكريم', 'عدد مفاصل الإنسان', 'خلق الإنسان | إعجازٌ وبيان',
    'الرتق والفتق', 'القلب يعقل ويفكر', 'والسماء ذات الحبك', 'ولو فتحنا عليهم باباً من السماء', 'وكل في فلك يسبحون',
    'التصعد في السماء', 'وإنا لموسعون', 'فكانت وردة كالدهان', 'تشريع الرضاعة حولين كاملين', 'فمحونا آية الليل وجعلنا آية النهار مبصرة',
    'أحكام الطهارة حكمة ومعجزة علمية', 'وجعلنا السماء سقفاً محفوظاً', 'عَجب الذنب معجزة علمية للحديث النبوي', 'بلى قادرين على أن نسوي بنانه',
    'مرج البحرين يلتقيان بينهما برزخٌ لا يبغيان',
]
video_html = open('html/video.html', encoding='utf-8', errors='replace').read()
for t in video_titles:
    assert t in video_html, t   # each title exists verbatim on the current page
videos = [{'slug': f'video-{i:02d}', 'section': 'videos', 'title': t, 'video_url': None} for i, t in enumerate(video_titles, 1)]

data = {'pages': [about], 'initiatives': inits, 'documents': docs, 'library': audio + videos}
json.dump(data, open('legacy-site.json', 'w', encoding='utf-8'), ensure_ascii=False, indent=2)
print('pages', 1, 'initiatives', len(inits), 'documents', len(docs), 'audio', len(audio), 'videos', len(videos))
