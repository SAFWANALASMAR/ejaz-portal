"""Map each audio title to its MP3 in document order: walk the HTML, remember the latest heading-like
text, and pair it with each .mp3 src that follows. Prints JSON lines; also lists video titles.
Usage: python -I audio_map.py audio.html video.html"""
import html
import json
import re
import sys
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8')


def tokens(raw):
    raw = re.sub(r'<(script|style|noscript)\b.*?</\1>', ' ', raw, flags=re.S)
    m = re.search(r'<main\b.*?</main>', raw, re.S)
    raw = m.group(0) if m else raw
    for t in re.finditer(r'<([^>]*)>|([^<]+)', raw):
        if t.group(2) is not None:
            text = re.sub(r'\s+', ' ', html.unescape(t.group(2))).strip()
            if text:
                yield ('text', text)
        else:
            src = re.search(r'\bsrc="([^"]+\.mp3)[^"]*"', t.group(1) or '')
            if src:
                yield ('mp3', urllib.parse.unquote(html.unescape(src.group(1))))


audio = open(sys.argv[1], encoding='utf-8', errors='replace').read()
last = None
pairs = []
for kind, value in tokens(audio):
    if kind == 'text':
        last = value
    elif kind == 'mp3':
        if not pairs or pairs[-1][1] != value:
            pairs.append((last, value))
for title, src in pairs:
    print(json.dumps({'title': title, 'file': src}, ensure_ascii=False))

# all mp3 links anywhere (to catch players the walk missed)
all_mp3 = sorted(set(urllib.parse.unquote(html.unescape(x)) for x in re.findall(r'(https?://[^"\'\s]+?\.mp3)', audio)))
print('ALL_MP3', len(all_mp3))
for x in all_mp3:
    print('  ', x)

if len(sys.argv) > 2:
    video = open(sys.argv[2], encoding='utf-8', errors='replace').read()
    print('VIDEO_TEXTS')
    for kind, value in tokens(video):
        if kind == 'text':
            print('  ', value)
