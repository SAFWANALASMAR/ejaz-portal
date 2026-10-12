"""Check each downloaded file against its title: PDF first-page text (pdftotext) and MP3 ID3 title/size.
Usage: python -I verify.py"""
import json
import os
import re
import subprocess
import sys
import unicodedata

sys.stdout.reconfigure(encoding='utf-8')
PDFTOTEXT = os.path.expanduser('~/scoop/apps/poppler/current/bin/pdftotext.exe')


def norm(s):
    s = unicodedata.normalize('NFKC', s)
    s = re.sub('[ً-ْـ]', '', s)  # tashkeel, tatweel
    s = s.replace('أ', 'ا').replace('إ', 'ا').replace('آ', 'ا').replace('ة', 'ه').replace('ى', 'ي')
    return re.sub(r'\s+', ' ', s)


print('== PDF')
for name in sorted(os.listdir('files/documents')):
    title = name[:-4].replace('-', ' ')
    text = subprocess.run([PDFTOTEXT, '-l', '2', '-enc', 'UTF-8', 'files/documents/' + name, '-'], capture_output=True).stdout.decode('utf-8', 'replace')
    words = [w for w in norm(title).split() if len(w) > 2]
    hits = sum(1 for w in words if w in norm(text))
    # pdftotext may reverse Arabic word order or letters; also test reversed words
    rev = norm(text)[::-1]
    hits_rev = sum(1 for w in words if w in rev)
    print(f'{max(hits, hits_rev)}/{len(words)} words  textlen={len(text.strip())}  {name}')

print('== MP3 (ID3)')


def id3_title(path):
    with open(path, 'rb') as f:
        head = f.read(10)
        if head[:3] != b'ID3':
            return None
        size = (head[6] << 21) | (head[7] << 14) | (head[8] << 7) | head[9]
        data = f.read(size)
    i = 0
    while i + 10 < len(data):
        fid = data[i:i + 4].decode('latin1')
        fsize = int.from_bytes(data[i + 4:i + 8], 'big')
        if not fid.strip('\x00') or fsize <= 0:
            break
        body = data[i + 10:i + 10 + fsize]
        if fid == 'TIT2':
            enc = body[0]
            raw = body[1:]
            return raw.decode({0: 'latin1', 1: 'utf-16', 2: 'utf-16-be', 3: 'utf-8'}.get(enc, 'latin1'), 'replace').strip('\x00')
        i += 10 + fsize
    return 'no TIT2'


for i in range(1, 31):
    p = f'files/audio/E3jaz-{i}.mp3'
    with open(p, 'rb') as f:
        start = f.read(3)
    print(i, 'mp3' if start in (b'ID3',) or start[:2] in (b'\xff\xfb', b'\xff\xf3', b'\xff\xf2') else start, id3_title(p))
