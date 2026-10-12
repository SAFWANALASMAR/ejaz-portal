"""Copy the files referenced by legacy-site.json from the local archive into ./files (outside git), where the
container and `php artisan import:legacy-site` can read them. Nothing is downloaded from ejaz.sa.
Usage (from this folder):
    python -I copy_from_archive.py "C:/Users/safwan/Downloads/أرشيف-موقع-جمعية-إعجاز/أرشيف-موقع-جمعية-إعجاز"
Not copied: 07 (lenis-init.min.js is code; Mask-3@2x.jpeg is missing in the archive), 09 (reference only), 10 (never)."""
import json
import os
import re
import shutil
import sys
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')
ARCHIVE = sys.argv[1]
HERE = os.path.dirname(os.path.abspath(__file__))
data = json.load(open(os.path.join(HERE, 'legacy-site.json'), encoding='utf-8'))

# The archive names audio "NN - title.mp3"; the import file uses the old site's names (E3jaz-N.mp3)
index = json.load(open(os.path.join(ARCHIVE, '01-البيانات', 'فهرس-الملفات.json'), encoding='utf-8'))
audio_source = {urllib.parse.unquote(e['الرابط_الأصلي']).rsplit('/', 1)[1]: e['الملف'] for e in index if e['الملف'].startswith('06-')}

copied = 0
for record in [*data['media'], *data['documents'], *data['library']]:
    target = record.get('file')
    if not target:
        continue
    name = target.rsplit('/', 1)[1]
    if target.startswith('files/documents/'):
        source = os.path.join('04-الوثائق-والسياسات', name)
    elif target.startswith('files/audio/'):
        source = audio_source[name]
    elif target.startswith('files/icons/'):
        source = os.path.join('05-الصور', 'أيقونات-SVG', name)
    else:
        source = os.path.join('05-الصور', name)
    destination = os.path.join(HERE, *target.split('/'))
    os.makedirs(os.path.dirname(destination), exist_ok=True)
    shutil.copyfile(os.path.join(ARCHIVE, source), destination)
    copied += 1

print('copied', copied)
