"""Contact sheet of the top part of each PDF's first page, numbered, to check each title visually."""
import os
import subprocess
import sys

from PIL import Image, ImageDraw

PDFTOPPM = os.path.expanduser('~/scoop/shims/pdftoppm.exe')
names = sorted(os.listdir('files/documents'))
tiles = []
for i, name in enumerate(names, 1):
    out = f'thumbs/t{i:02d}'
    subprocess.run([PDFTOPPM, '-png', '-r', '55', '-f', '1', '-l', '1', '-singlefile', 'files/documents/' + name, out], check=True)
    im = Image.open(out + '.png').convert('RGB')
    im = im.crop((0, int(im.height * 0.18), im.width, int(im.height * 0.55)))
    im.thumbnail((440, 300))
    canvas = Image.new('RGB', (450, 330), 'white')
    canvas.paste(im, (5, 25))
    ImageDraw.Draw(canvas).text((8, 4), f'#{i:02d}', fill='red')
    tiles.append(canvas)
cols = 4
rows = (len(tiles) + cols - 1) // cols
sheet = Image.new('RGB', (cols * 450, rows * 330), 'white')
for k, t in enumerate(tiles):
    sheet.paste(t, ((k % cols) * 450, (k // cols) * 330))
sheet.save('thumbs/sheet.png')
with open('thumbs/index.txt', 'w', encoding='utf-8') as f:
    for i, n in enumerate(names, 1):
        f.write(f'{i:02d} {n}\n')
print('ok', len(tiles))
