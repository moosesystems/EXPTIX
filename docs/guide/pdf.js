// Make print-ready PDFs of every guide page: node pdf.js  (needs playwright-core and a Chromium)
// Sheets must fit on one Letter page; the script reports any that do not.
const fs = require('fs'), path = require('path');
const { chromium } = require('playwright-core');
const HERE = __dirname, OUT = path.join(HERE, 'pdf');
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const man = JSON.parse(fs.readFileSync(path.join(HERE, 'manifest.json'), 'utf8'));
  const b = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {});
  const p = await b.newPage();
  const over = [];
  for (const d of man) {
    await p.goto('file://' + path.join(HERE, d.file + '.html'), { waitUntil: 'load' });
    await p.emulateMedia({ media: 'print', colorScheme: 'light' });
    const pdf = await p.pdf({ path: path.join(OUT, d.file + '.pdf'), format: 'Letter', printBackground: true, preferCSSPageSize: true });
    const pages = (pdf.toString('latin1').match(/\/Type\s*\/Page[^s]/g) || []).length;
    if (d.kind === 'sheet' && pages > 1) over.push(`${d.file} (${pages} pages)`);
  }
  await b.close();
  console.log(`pdf: ${man.length} files` + (over.length ? `\nsheets over one page: ${over.join(', ')}` : '\nall sheets fit on one page'));
})().catch(e => { console.error(e); process.exit(1); });
