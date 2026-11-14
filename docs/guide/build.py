#!/usr/bin/env python3
"""Build the ExpTix how-to-use library.

Reads docs/guide/src/*.md (front matter + Markdown) and writes:
  docs/guide/<slug>.html   web pages
  docs/guide/index.html    the library home
  docs/guide/docx/<slug>.docx   editable Word copies (needs pandoc)
PDFs are made afterwards by pdf.js (needs Node and playwright-core), see README in this folder.
"""
import html, json, os, pathlib, re, subprocess, sys

HERE = pathlib.Path(__file__).resolve().parent
SRC = HERE / 'src'
FMT = 'markdown+gfm_auto_identifiers+pipe_tables+emoji'

LANGS = {'en': 'English', 'fr': 'Français', 'es': 'Español', 'tl': 'Tagalog'}
UI = {'fr': {'sheet': 'Fiche d\'une page', 'for': 'Pour', 'pdf': 'PDF à imprimer', 'print': 'Imprimer'},
      'es': {'sheet': 'Hoja de una página', 'for': 'Para', 'pdf': 'PDF para imprimir', 'print': 'Imprimir'},
      'tl': {'sheet': 'Isang pahinang gabay', 'for': 'Para sa', 'pdf': 'PDF na ipi-print', 'print': 'I-print'}}
KIND = {'sheet': 'One-page sheet', 'faq': 'FAQ', 'playbook': 'Playbook', 'glossary': 'Glossary', 'guide': 'Guide'}
GROUPS = [
    ('frontline', 'Frontline staff', 'One page each. Big steps, few words, made to print and pin up.'),
    ('leaders', 'Supervisors, managers and executives', 'What you get, your first week, the numbers to watch and the decisions you own.'),
    ('size', 'By property size', 'From a three-room lodge to a group with thousands of rooms, plus a 90-day rollout plan.'),
    ('faq', 'Questions and answers', 'Straight answers for staff, managers, executives and IT.'),
    ('reference', 'Reference', 'Words we use, and where to start.'),
]
# screenshot shown beside each page: (path relative to docs/, caption, is_phone)
FIG = {
    'sheet-first-day-everyone': ('images/roles/housekeeping-mobile-my-work.jpg', 'My work on a phone: tick On shift, then your jobs appear', 1),
    'sheet-room-attendant': ('images/roles/housekeeping-mobile-board.jpg', 'Your rooms on the phone. Red dirty, orange cleaning, blue clean, green inspected', 1),
    'sheet-houseperson-laundry': ('images/roles/housekeeping-mobile-my-work.jpg', 'My work: the jobs given to you', 1),
    'sheet-maintenance-tech': ('images/roles/engineering-mobile-my-work.jpg', 'My work: most urgent first', 1),
    'sheet-cook-steward': ('images/roles/kitchen-mobile-food.jpg', 'Food safety: tap a unit, type the reading, save', 1),
    'sheet-server-fb': ('images/roles/kitchen-dashboard.jpg', 'Home: open jobs, alerts and announcements', 0),
    'sheet-front-desk-agent': ('images/roles/frontdesk-new-request.jpg', 'New request: quick picks, the room, the guest', 0),
    'sheet-security-officer': ('images/roles/security-mobile-my-work.jpg', 'My work: your jobs with the clock', 1),
    'sheet-contractor': ('images/roles/contractor-mobile-my-work.jpg', 'A contractor sees only their own jobs', 1),
    'sheet-guest-card': ('images/mobile-guest-portal.png', 'What a guest sees after scanning the room QR code', 1),
    'sheet-supervisor': ('images/roles/housekeeping-board.jpg', 'The housekeeping board with attendant workload', 0),
    'sheet-department-manager': ('images/roles/engineering-pm.jpg', 'Preventive maintenance schedules', 0),
    'sheet-general-manager': ('images/roles/gm-dashboard.jpg', 'The GM home screen', 0),
    'sheet-cfo': ('images/roles/gm-reports.jpg', 'Reports: response, SLA misses, ratings, labour and parts', 0),
    'sheet-vp-operations': ('images/roles/gm-reports.jpg', 'Reports export to CSV for portfolio comparison', 0),
    'sheet-owner-auditor': ('images/roles/auditor-audit-log.jpg', 'The audit log with integrity check', 0),
    'sheet-it-admin': ('images/roles/auditor-compliance.jpg', 'Security and compliance page', 0),
}


def parse(p: pathlib.Path):
    t = p.read_text(encoding='utf-8')
    m = re.match(r'^---\n(.*?)\n---\n', t, re.S)
    meta = {}
    if m:
        for line in m.group(1).splitlines():
            line = re.sub(r'\s+#.*$', '', line)
            if ':' in line:
                k, v = line.split(':', 1)
                meta[k.strip()] = v.strip().strip('"\'')
        t = t[m.end():]
    name = p.name[:-3]
    lang = 'en'
    mm = re.match(r'^(.*)\.([a-z]{2})$', name)
    if mm:
        name, lang = mm.group(1), mm.group(2)
    meta.setdefault('lang', lang)
    meta['slug'] = name
    meta['file'] = p.name[:-3]
    meta['order'] = int(meta.get('order', 50) or 50)
    return meta, t


def md2html(md: str) -> str:
    r = subprocess.run(['pandoc', '-f', FMT, '-t', 'html5', '--no-highlight'], input=md, capture_output=True, text=True, check=True)
    out = r.stdout
    out = re.sub(r'href="([a-z0-9-]+(?:\.[a-z]{2})?)\.md(#[^"]*)?"', lambda m: f'href="{m.group(1)}.html{m.group(2) or ""}"', out)
    return out


E = html.escape
HEAD = '''<!doctype html>
<html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{title}</title><meta name="description" content="{desc}">
<link rel="icon" href="../images/logo.svg" type="image/svg+xml"><link rel="stylesheet" href="guide.css"></head>
<body class="{cls}">
<header class="top"><div class="in"><a class="brand" href="../index.html"><img src="../images/logo.svg" alt="">ExpTix</a>
<a href="index.html">Guides</a><a href="faq-staff.html">FAQ</a><a href="../DEPLOYMENT.html">Deploy</a><a href="https://github.com/moosesystems/EXPTIX">GitHub</a></div></header>
'''
# Optional site branding for a mirror: EXPTIX_BRAND_FILE=/path/brand.json with keys "footer_html" and "printfoot".
_brand = json.loads(pathlib.Path(os.environ['EXPTIX_BRAND_FILE']).read_text()) if os.environ.get('EXPTIX_BRAND_FILE') else {}
BY = _brand.get('footer_html', '<span>ExpTix · free and open-source hotel operations · GNU AGPL v3</span>')
PRINTFOOT = _brand.get('printfoot', 'ExpTix · free and open-source hotel operations · moosesystems.github.io/EXPTIX')
FOOT = '''<footer class="foot"><div class="in">''' + BY + '''
<span><a href="index.html">All guides</a> · <a href="../DEPLOYMENT.html">Deployment guide</a> · <a href="https://github.com/moosesystems/EXPTIX">Source</a></span></div></footer>
</body></html>
'''


def page(meta, body_html, siblings):
    slug, lang, kind, group = meta['slug'], meta['lang'], meta.get('kind', 'guide'), meta.get('group', 'reference')
    title = meta.get('title', slug)
    fig = FIG.get(slug)
    out = [HEAD.format(lang=E(lang), title=E(f'{title} · ExpTix guides'), desc=E(meta.get('subtitle', '') or meta.get('audience', '')), cls=f'kind-{kind} group-{group}')]
    out.append('<main class="wrap">')
    out.append(f'<p class="crumbs"><a href="index.html">Guides</a> › {E(dict((g, n) for g, n, _ in GROUPS).get(group, ""))}</p>')
    out.append(f'<div class="doc{"" if fig else " nofig"}"><article>')
    out.append('<div class="titleblock">')
    out.append(f'<h1>{E(title)}</h1>')
    if meta.get('subtitle'):
        out.append(f'<p class="sub">{E(meta["subtitle"])}</p>')
    ui = UI.get(lang, {})
    chips = [f'<span class="chip k">{E(ui.get(kind) or KIND.get(kind, kind))}</span>']
    if meta.get('audience'):
        chips.append(f'<span class="chip">{E(ui.get("for", "For"))}: {E(meta["audience"])}</span>')
    out.append('<div class="chips">' + ''.join(chips) + '</div></div>')
    if len(siblings) > 1:
        cur = ' aria-current="page"'
        out.append('<p class="langs">' + ' '.join(
            f'<a href="{s}.html" hreflang="{l}"{cur if l == lang else ""}>{E(LANGS.get(l, l))}</a>' for l, s in siblings) + '</p>')
    f = meta['file']
    out.append('<div class="dl">'
               f'<a href="pdf/{f}.pdf">⬇ {E(ui.get("pdf", "PDF to print"))}</a>'
               f'<a href="docx/{f}.docx">⬇ Word</a>'
               f'<a href="src/{f}.md">⬇ Markdown</a>'
               f'<button type="button" onclick="print()">🖨 {E(ui.get("print", "Print"))}</button></div>')
    out.append(f'<div class="body">{body_html}</div>')
    out.append(f'<p class="printfoot">{E(PRINTFOOT)}</p>')
    out.append('</article>')
    if fig:
        src, cap, phone = fig
        out.append(f'<figure class="fig {"phone" if phone else "desk"}"><img src="../{src}" alt="{E(cap)}" loading="lazy"><figcaption>{E(cap)}</figcaption></figure>')
    out.append('</div></main>')
    out.append(FOOT)
    return '\n'.join(out)


def index(docs, route_html):
    out = [HEAD.format(lang='en', title='ExpTix guides: how to use it, for everyone', desc='How to use ExpTix: one-page sheets for every role, playbooks by property size, FAQs and a glossary.', cls='index')]
    out.append('<section class="hero"><div class="wrap"><h1>How to use ExpTix.<br><span class="grad">For everyone in the building.</span></h1>'
               '<p>From the room attendant on day one to the CFO and the VP of operations. From a three-room lodge to a group with thousands of rooms. Every guide prints on paper and downloads as PDF or Word.</p></div></section>')
    out.append('<main class="wrap">')
    for g, name, blurb in GROUPS:
        items = sorted([d for d in docs if d.get('group') == g and d['lang'] == 'en' and d['slug'] != 'guide-index'], key=lambda d: (d['order'], d['slug']))
        if not items:
            continue
        out.append(f'<section class="grp" id="{g}"><h2>{E(name)}</h2><p>{E(blurb)}</p><div class="cards">')
        for d in items:
            extra = [LANGS[l] for l in d.get('_langs', []) if l != 'en']
            sub = d.get('subtitle') or d.get('audience', '')
            out.append(f'<a class="gc" href="{d["slug"]}.html"><b>{E(d.get("title", d["slug"]))}</b><span>{E(sub)}</span>'
                       f'<em>{E(KIND.get(d.get("kind", ""), ""))}{" · also in " + ", ".join(extra) if extra else ""}</em></a>')
        out.append('</div></section>')
    if route_html:
        out.append(f'<section class="route body"><h2 style="border:0;margin-top:0">Not sure where to start?</h2>{route_html}</section>')
    out.append('</main>')
    out.append(FOOT)
    return '\n'.join(out)


def main():
    files = sorted(SRC.glob('*.md'))
    if not files:
        sys.exit('no sources in ' + str(SRC))
    docs, bodies = [], {}
    for p in files:
        meta, md = parse(p)
        docs.append(meta)
        bodies[meta['file']] = md
    langs = {}
    for d in docs:
        langs.setdefault(d['slug'], []).append((d['lang'], d['file']))
    for d in docs:
        d['_langs'] = sorted(l for l, _ in langs[d['slug']])
    (HERE / 'docx').mkdir(exist_ok=True)
    route = ''
    for d in docs:
        sib = sorted(langs[d['slug']], key=lambda x: list(LANGS).index(x[0]) if x[0] in LANGS else 9)
        body = md2html(bodies[d['file']])
        if d['slug'] == 'guide-index':
            route = re.sub(r'^\s*<h1[^>]*>.*?</h1>', '', body, flags=re.S)
        (HERE / f'{d["file"]}.html').write_text(page(d, body, sib), encoding='utf-8')
        md_full = f'# {d.get("title", d["slug"])}\n\n' + (f'*{d["subtitle"]}*\n\n' if d.get('subtitle') else '') + bodies[d['file']]
        subprocess.run(['pandoc', '-f', FMT, '-t', 'docx', '--metadata', f'lang={d["lang"]}', '-o', str(HERE / 'docx' / f'{d["file"]}.docx')],
                       input=md_full, text=True, check=True)
    (HERE / 'index.html').write_text(index(docs, route), encoding='utf-8')
    manifest = [{'file': d['file'], 'kind': d.get('kind'), 'group': d.get('group'), 'lang': d['lang']} for d in docs]
    (HERE / 'manifest.json').write_text(json.dumps(manifest, indent=1), encoding='utf-8')
    print(f'built {len(docs)} pages, index, {len(docs)} docx')


if __name__ == '__main__':
    main()
