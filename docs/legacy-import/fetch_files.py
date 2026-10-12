"""Download the files referenced by legacy-site.json from the current site into ./files (outside git).
Each record's first legacy path is its original URL on ejaz.sa. Usage (from this folder):
    python -I fetch_files.py
The downloaded files stay out of git (docs/legacy-import/files is ignored)."""
import json
import os
import sys
import urllib.parse
import urllib.request

sys.stdout.reconfigure(encoding='utf-8')
data = json.load(open('legacy-site.json', encoding='utf-8'))
records = [r for section in ('documents', 'library') for r in data.get(section, []) if r.get('file')]
for r in records:
    url = 'https://ejaz.sa' + urllib.parse.quote(r['legacy_paths'][0])
    os.makedirs(os.path.dirname(r['file']), exist_ok=True)
    if os.path.exists(r['file']):
        print('exists', r['file'])
        continue
    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, timeout=300) as resp, open(r['file'], 'wb') as out:
        out.write(resp.read())
    head = open(r['file'], 'rb').read(5)
    ok = head.startswith(b'%PDF-') if r['file'].endswith('.pdf') else head[:3] == b'ID3' or head[:2] in (b'\xff\xfb', b'\xff\xf3')
    print('ok' if ok else 'CHECK', r['file'])
