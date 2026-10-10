"""Extract visible text blocks from an Elementor page, in document order, from the main content only.
Usage: python -I extract.py page.html"""
import html
import re
import sys
from html.parser import HTMLParser


class Text(HTMLParser):
    SKIP = {'script', 'style', 'noscript', 'svg', 'header', 'footer', 'nav'}
    BLOCK = {'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'div', 'span', 'a', 'br', 'section', 'figcaption', 'td', 'th'}

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.skip = 0
        self.parts = []
        self.buf = []

    def flush(self):
        t = re.sub(r'\s+', ' ', ''.join(self.buf)).strip()
        if t:
            self.parts.append(t)
        self.buf = []

    def handle_starttag(self, tag, attrs):
        if tag in self.SKIP:
            self.skip += 1
        if tag in self.BLOCK:
            self.flush()

    def handle_endtag(self, tag):
        if tag in self.SKIP and self.skip:
            self.skip -= 1
        if tag in self.BLOCK:
            self.flush()

    def handle_data(self, data):
        if not self.skip:
            self.buf.append(data)


raw = open(sys.argv[1], encoding='utf-8', errors='replace').read()
# main content: Elementor page wrapper if present
m = re.search(r'<main\b.*?</main>', raw, re.S) or re.search(r'data-elementor-type="wp-page".*', raw, re.S)
body = m.group(0) if m else raw
p = Text()
p.feed(body)
p.flush()
seen = []
for t in p.parts:
    if not seen or seen[-1] != t:
        seen.append(t)
sys.stdout.reconfigure(encoding='utf-8')
print('\n'.join(seen))
