// Usage: node build.mjs <guide.md> <out.html> <kicker> <runningHeader>
import { readFileSync, writeFileSync } from 'node:fs';

const [src, out, kicker, running] = process.argv.slice(2);
const lines = readFileSync(src, 'utf8').replace(/\r/g, '').split('\n');

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const PILL = { High: 'high', Medium: 'med', Low: 'low' };
function inline(s) {
  // keep <br> as line breaks, escape everything else
  const parts = s.split(/<br\s*\/?>/i).map((p) => {
    let t = esc(p);
    t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
    t = t.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    t = t.replace(/(^|[\s(])\*([^*\s][^*]*)\*/g, '$1<em>$2</em>');
    t = t.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2">$1</a>');
    t = t.replace(/\b(High|Medium|Low)\b(?=\s*$)/, (m) => `<span class="pill ${PILL[m]}">${m}</span>`);
    return t;
  });
  return parts.join('<br>');
}
function cellSteps(s) {
  const items = s.split(/<br\s*\/?>/i).map((x) => x.trim()).filter(Boolean);
  if (items.length && items.every((x) => /^\d+\.\s/.test(x))) {
    return '<ol>' + items.map((x) => `<li>${inline(x.replace(/^\d+\.\s*/, ''))}</li>`).join('') + '</ol>';
  }
  return inline(s);
}
const splitRow = (l) => l.trim().replace(/^\|/, '').replace(/\|$/, '').split(/(?<!\\)\|/).map((c) => c.trim().replace(/\\\|/g, '|'));

let html = '';
let title = '';
let i = 0;
let headerDone = false;
while (i < lines.length) {
  const l = lines[i];
  if (!l.trim()) { i++; continue; }
  if (l.startsWith('# ')) {
    title = l.slice(2).trim();
    i++;
    // subtitle = next non-empty paragraph
    while (i < lines.length && !lines[i].trim()) i++;
    let sub = '';
    if (i < lines.length && !lines[i].startsWith('**Where') && !lines[i].startsWith('#')) {
      while (i < lines.length && lines[i].trim()) sub += ' ' + lines[i++].trim();
    }
    html += `<header class="title"><div class="kicker">${esc(kicker)}</div><h1>${inline(title)}</h1>${sub ? `<p class="sub">${inline(sub.trim())}</p>` : ''}</header>`;
    continue;
  }
  if (l.startsWith('```')) {
    const lang = l.slice(3).trim();
    const code = [];
    i++;
    while (i < lines.length && !lines[i].startsWith('```')) code.push(lines[i++]);
    i++;
    html += lang === 'mermaid'
      ? `<div class="diagram"><pre class="mermaid">${esc(code.join('\n'))}</pre></div>`
      : `<pre class="code">${esc(code.join('\n'))}</pre>`;
    continue;
  }
  if (!headerDone && (l.startsWith('**Where') || l.startsWith('**Scope'))) {
    let p = '';
    while (i < lines.length && lines[i].trim()) p += ' ' + lines[i++].trim();
    html += `<div class="where">${inline(p.trim())}</div>`;
    headerDone = true;
    continue;
  }
  const h = l.match(/^(#{2,4})\s+(.*)/);
  if (h) { const n = h[1].length; html += `<h${n}>${inline(h[2])}</h${n}>`; i++; continue; }
  if (/^>\s*flow:/i.test(l)) {
    const steps = l.replace(/^>\s*flow:\s*/i, '').split(/\s*->\s*/);
    html += '<div class="flow">' + steps.map((s) => `<div>${inline(s)}</div>`).join('<span>&rarr;</span>') + '</div>';
    i++; continue;
  }
  if (l.startsWith('>')) {
    let p = [];
    while (i < lines.length && lines[i].startsWith('>')) p.push(lines[i++].replace(/^>\s?/, ''));
    html += `<div class="note">${p.filter(Boolean).map((x) => `<p>${inline(x)}</p>`).join('')}</div>`;
    continue;
  }
  if (l.trim().startsWith('|')) {
    const rows = [];
    while (i < lines.length && lines[i].trim().startsWith('|')) rows.push(lines[i++]);
    const head = splitRow(rows[0]);
    const body = rows.slice(2).map(splitRow);
    const isScen = head[0] === 'ID' && head.includes('Steps');
    if (isScen) {
      const pi = head.indexOf('Priority');
      const si = head.indexOf('Scenario');
      const sti = head.indexOf('Steps');
      const ei = head.findIndex((x) => /^Expected/.test(x));
      html += '<table class="scen"><colgroup><col style="width:9%"><col style="width:18%"><col style="width:33%"><col style="width:40%"></colgroup><thead><tr><th></th><th>Scenario</th><th>Steps</th><th>Expected result</th></tr></thead><tbody>';
      for (const r of body) {
        const pr = (r[pi] || '').trim();
        const pill = PILL[pr] ? ` <span class="pill ${PILL[pr]}">${pr}</span>` : '';
        html += `<tr><td class="id">${esc(r[0])}</td><td class="sc"><strong>${inline(r[si] || '')}</strong>${pill}</td><td>${cellSteps(r[sti] || '')}</td><td>${inline(r[ei] || '')}</td></tr>`;
      }
      html += '</tbody></table>';
    } else {
      html += '<table><thead><tr>' + head.map((c) => `<th>${inline(c)}</th>`).join('') + '</tr></thead><tbody>';
      for (const r of body) html += '<tr>' + r.map((c) => `<td>${cellSteps(c)}</td>`).join('') + '</tr>';
      html += '</tbody></table>';
    }
    continue;
  }
  if (/^\s*([-*]|\d+\.)\s/.test(l)) {
    // list (supports one nested level by indentation)
    const ordered = /^\s*\d+\.\s/.test(l);
    const items = [];
    while (i < lines.length && (/^\s*([-*]|\d+\.)\s/.test(lines[i]) || (/^\s{2,}\S/.test(lines[i]) && items.length))) {
      const m = lines[i].match(/^(\s*)([-*]|\d+\.)\s+(.*)/);
      if (m) items.push({ depth: m[1].length >= 2 ? 1 : 0, text: m[3] });
      else items[items.length - 1].text += ' ' + lines[i].trim();
      i++;
    }
    const tag = ordered ? 'ol' : 'ul';
    let out = `<${tag}>`;
    let open = false;
    for (const it of items) {
      if (it.depth === 1) {
        if (!open) { out = out.replace(/<\/li>$/, ''); out += '<ul>'; open = true; }
        out += `<li>${inline(it.text)}</li>`;
      } else {
        if (open) { out += '</ul></li>'; open = false; }
        out += `<li>${inline(it.text)}</li>`;
      }
    }
    if (open) out += '</ul></li>';
    html += out + `</${tag}>`;
    continue;
  }
  let p = '';
  while (i < lines.length && lines[i].trim() && !/^(#|\||>|```|\s*([-*]|\d+\.)\s)/.test(lines[i])) p += ' ' + lines[i++].trim();
  html += `<p>${inline(p.trim())}</p>`;
}

const css = `
@page { size: A4; margin: 20mm 17mm 18mm 17mm; }
* { box-sizing: border-box; }
body { font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif; font-size: 9.6pt; line-height: 1.5; color: #1f2933; margin: 0; }
.title { border-left: 5px solid #0d4a7c; padding: 2px 0 4px 18px; margin: 0 0 18px; }
.kicker { font-size: 7.6pt; font-weight: 700; letter-spacing: .18em; color: #0d4a7c; text-transform: uppercase; }
h1 { font-size: 22pt; margin: 4px 0 6px; color: #17212b; }
.sub { color: #5b6773; font-size: 11pt; margin: 0; }
.where { background: #e7f0f8; border-left: 4px solid #0d4a7c; padding: 10px 14px; margin: 0 0 18px; }
h2 { color: #0d4a7c; font-size: 14pt; border-bottom: 1px solid #d5dde5; padding-bottom: 5px; margin: 22px 0 10px; page-break-after: avoid; }
h3 { font-size: 11pt; margin: 16px 0 6px; page-break-after: avoid; }
h4 { font-size: 10pt; margin: 12px 0 4px; page-break-after: avoid; }
p { margin: 0 0 8px; }
code { font-family: "DejaVu Sans Mono", Consolas, monospace; font-size: 8.6pt; background: #eef2f6; padding: 0 3px; border-radius: 3px; }
a { color: #0d4a7c; }
ul, ol { margin: 0 0 8px; padding-left: 22px; }
li { margin-bottom: 3px; }
table { width: 100%; border-collapse: collapse; margin: 6px 0 12px; font-size: 8.6pt; }
th { background: #0d4a7c; color: #fff; text-align: left; padding: 6px 7px; font-weight: 700; }
td { padding: 6px 7px; vertical-align: top; border-bottom: 1px solid #dde3e9; }
tbody tr:nth-child(even) td { background: #f5f7fa; }
tr { page-break-inside: avoid; }
thead { display: table-header-group; }
td ol, td ul { margin: 0; padding-left: 16px; }
td li { margin-bottom: 2px; }
.scen td.id { color: #0d4a7c; font-weight: 700; white-space: nowrap; }
.pill { display: inline-block; font-size: 7.4pt; font-weight: 700; padding: 0 5px; border-radius: 3px; line-height: 1.5; }
.pill.high { background: #fbe3e3; color: #b42318; }
.pill.med { background: #fdf0d5; color: #a15c07; }
.pill.low { background: #e3f4e8; color: #1e7a3c; }
.note { background: #e7f0f8; border-left: 4px solid #0d4a7c; padding: 8px 12px; margin: 6px 0 12px; }
.note p:last-child { margin: 0; }
.flow { display: flex; align-items: center; gap: 6px; margin: 6px 0 12px; }
.flow div { flex: 1; border: 1px solid #c9d3dd; border-radius: 4px; background: #f5f7fa; padding: 7px 6px; text-align: center; font-size: 8.4pt; }
.flow span { color: #0d4a7c; }
.diagram { margin: 8px 0 14px; text-align: center; page-break-inside: avoid; }
.diagram svg { max-width: 100%; height: auto; }
pre.code { font-family: "DejaVu Sans Mono", Consolas, monospace; font-size: 8.4pt; background: #eef2f6; padding: 8px 10px; white-space: pre-wrap; }
`;
// mermaid diagrams render in the browser; MERMAID_SRC points the print build at a local copy
const mm = html.includes('class="mermaid"') && process.env.MERMAID_SRC
  ? `<script src="${process.env.MERMAID_SRC}"></script><script>mermaid.initialize({startOnLoad:false,theme:'neutral',flowchart:{useMaxWidth:true}});mermaid.run().then(()=>{document.title+=' ';document.body.dataset.ready='1'});</script>`
  : '';
writeFileSync(out, `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>${esc(title)}</title><style>${css}</style></head><body>${html}${mm}</body></html>`);
console.log(JSON.stringify({ title, running }));
