# ExpTix how-to-use guides

The source of every guide is a Markdown file in `src/`. Edit those, never the generated `.html`, `pdf/` or `docx/` files.

Rebuild after any change:

```bash
cd docs/guide
python3 build.py          # web pages, index.html and docx/ (needs pandoc 3)
node pdf.js               # pdf/ (needs playwright-core and Chromium; set CHROMIUM=/path/to/chrome if needed)
```

`pdf.js` warns when a one-page sheet runs onto a second page. Trim the Markdown until it fits.

## Adding a guide

1. Copy a file in `src/` with the same `kind` and `group` and change the front matter (`title`, `subtitle`, `audience`, `order`).
2. Follow the writing rules: plain words, active voice, no em dashes, Canadian spelling, button names in **bold** exactly as the app shows them. Frontline sheets stay under about 180 words.
3. Add a screenshot for it in `FIG` in `build.py` if it should show one.

## Translations

Name the file `<slug>.<lang>.md` (for example `sheet-room-attendant.fr.md`) and set `lang:` in the front matter. Keep button names in English in bold, because the app screens are English only today, and give the meaning in brackets the first time. Every machine-assisted translation must say so at the top until a native speaker has checked it. Translations into Inuktitut, Inuinnaqtun and other languages are very welcome from native speakers.
