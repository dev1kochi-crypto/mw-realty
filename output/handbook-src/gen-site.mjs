// Generates the web version of the tester handbook into public/docs/tester-handbook.
// New guides (with markdown source) become HTML pages; older guides (PDF only) render their pages in-site.
import { readFileSync, writeFileSync, copyFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const SCRATCH = 'C:/Users/User/AppData/Local/Temp/claude/c--Laravel-mw-realty/e2285a8f-4858-45a7-83e5-c35fa6db4434/scratchpad';
const OUT = 'C:/Laravel/mw-realty/public/docs/tester-handbook';
mkdirSync(join(OUT, 'assets'), { recursive: true });
copyFileSync(join(SCRATCH, 'render/pdf.min.js'), join(OUT, 'assets/pdf.min.js'));
copyFileSync(join(SCRATCH, 'render/pdf.worker.min.js'), join(OUT, 'assets/pdf.worker.min.js'));
copyFileSync(join(SCRATCH, 'render/mermaid.min.js'), join(OUT, 'assets/mermaid.min.js'));

const G = [
  ['P1', 'brd-frd', 'Business & Functional Requirements (BRD / FRD)', 'Project', 'Objectives, scope, requirements, data flow diagrams, wireframes'],
  ['P2', 'srs-fsd', 'Functional Specification (SRS / FSD)', 'Project', 'Functionality and workflows of every module'],
  ['P3', 'access-roles', 'Application Access, Roles & Permissions', 'Project', 'Environments, test accounts, roles, permission matrix'],
  ['P4', 'test-data', 'Test Data', 'Project', 'Sample data for each business scenario, boundary values'],
  ['P5', 'business-rules', 'Business Rules & Expected Results', 'Project', 'Where each module guide lists its rules and expected results'],
  ['P6', 'environment', 'Environment & Setup', 'Project', 'Browsers, devices, server setup, configuration'],
  ['P7', 'apis-batch-jobs', 'APIs & Batch Jobs', 'Project', 'API endpoints, webhooks, scheduled and queued jobs, sample logs'],
  ['P8', 'automation-tools', 'Test Automation Tools', 'Project', 'What exists today and the recommended tool stack'],
  ['00', 'mw-realty-project-overview', 'Project overview', 'Start here', 'What the product is, who uses it, how the parts work together'],
  ['27', 'website-pages', 'Home, Search, Agents, Agencies, Calculators & Explore', 'Website', 'Every page in the website menu'],
  ['26', 'website-compare-insights-chat', 'Compare, Insights & AI Chat', 'Website', 'Property compare, market insights on listings, currency rates, multilingual AI chat'],
  ['01', 'dashboard-test-guide', 'Dashboard', 'CRM'], ['02', 'leads-test-guide', 'Leads', 'CRM'],
  ['03', 'lead-insights-test-guide', 'Lead Insights', 'CRM'], ['04', 'website-leads-test-guide', 'Website Leads', 'CRM'],
  ['05', 'master-data-test-guide', 'Master Data', 'CRM'], ['06', 'integrations-test-guide', 'Integrations', 'CRM'],
  ['07', 'properties-test-guide', 'Properties', 'CRM', 'Bulk import and Write with AI: see 25'],
  ['25', 'property-import-ai-copy', 'Property Import & AI Copy', 'CRM', 'Excel bulk import, Write with AI, Property Finder changes (add-on to 07)'],
  ['08', 'commercial-test-guide', 'Commercial', 'CRM'],
  ['09', 'listing-permits-test-guide', 'Listing Permits', 'CRM', 'Links to Listing Review: see 24'],
  ['24', 'listing-review', 'Listing Review', 'CRM', 'Super Admin queue for duplicate and risk-flagged listings'],
  ['10', 'premium-test-guide', 'Premium', 'CRM'], ['11', 'advertisements-test-guide', 'Advertisements', 'CRM'],
  ['12', 'sold-listings-test-guide', 'Sold Listings', 'CRM'], ['13', 'marketing-properties-test-guide', 'Marketing Properties', 'CRM'],
  ['14', 'agents-test-guide', 'Agents', 'CRM'], ['15', 'nearby-places-test-guide', 'Nearby Places', 'CRM'],
  ['16', 'listing-settings-test-guide', 'Listing Settings', 'CRM'], ['17', 'reports-test-guide', 'Reports', 'CRM'],
  ['18', 'help-desk-test-guide', 'Help Desk', 'CRM'], ['19', 'contact-us-test-guide', 'Contact Us', 'CRM'],
  ['20', 'credits-test-guide', 'Credits', 'CRM'], ['21', 'my-profile-test-guide', 'My Profile', 'CRM'],
  ['22', 'security-test-guide', 'Security', 'CRM'], ['23', 'plans-test-guide', 'Plans', 'CRM'],
].map(([n, slug, title, group, note]) => ({ n, file: `${n}-${slug}`, title, group, note }))
  // a project document appears only once its page has been built
  .filter((d) => (d.group !== 'Project' && d.n !== '27') || existsSync(join(SCRATCH, 'html', `${d.file}.html`)));

const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const GROUPS = ['Start here', 'Project', 'Website', 'CRM'];
const GROUP_LABEL = { Project: 'Project documents', Website: 'Website modules', 'Start here': 'Start here', CRM: 'CRM modules' };

const CSS = `
:root {
  --bg: #f6f8fb; --card: #ffffff; --fg: #1f2933; --muted: #5b6773; --line: #dbe2ea; --stripe: #f5f7fa;
  --accent: #0d4a7c; --accent-fg: #ffffff; --accent-soft: #e7f0f8; --new: #1e7a3c; --new-soft: #e3f4e8;
  --code: #eef2f6; --high: #b42318; --high-soft: #fbe3e3; --med: #a15c07; --med-soft: #fdf0d5;
  --sans: "Segoe UI", "DejaVu Sans", Arial, sans-serif; --mono: Consolas, "DejaVu Sans Mono", monospace;
}
@media (prefers-color-scheme: dark) { :root {
  --bg: #0f151c; --card: #17202a; --fg: #e6ecf2; --muted: #9aa7b4; --line: #2a3644; --stripe: #1b2530;
  --accent: #7db4e6; --accent-fg: #0f151c; --accent-soft: #1b2b3c; --new: #7fd49a; --new-soft: #183326;
  --code: #22303d; --high: #ff9b8f; --high-soft: #3a1d1b; --med: #f5c26b; --med-soft: #3a2c12; color-scheme: dark } }
* { box-sizing: border-box; }
html { scroll-padding-top: 16px; }
body { margin: 0; background: var(--bg); color: var(--fg); font-family: var(--sans); font-size: 15px; line-height: 1.55; }
a { color: var(--accent); }
.layout { display: grid; grid-template-columns: 270px minmax(0, 1fr); min-height: 100vh; }
nav.side { background: var(--card); border-right: 1px solid var(--line); padding: 20px 14px; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
.brand { display: block; text-decoration: none; color: var(--fg); padding: 0 8px 14px; border-bottom: 1px solid var(--line); margin-bottom: 10px; }
.brand small { display: block; font-size: 11px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: var(--accent); }
.brand strong { font-size: 17px; }
nav.side h4 { font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); margin: 14px 8px 4px; }
nav.side a.item { display: flex; gap: 8px; padding: 5px 8px; border-radius: 5px; text-decoration: none; color: var(--fg); font-size: 14px; }
nav.side a.item span { font-family: var(--mono); color: var(--muted); font-size: 12px; padding-top: 2px; }
nav.side a.item:hover { background: var(--stripe); }
nav.side a.item.on { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
.menu-btn { display: none; }
main { padding: 32px 40px 64px; min-width: 0; }
.doc { max-width: 920px; margin: 0 auto; }
.title { border-left: 5px solid var(--accent); padding: 2px 0 4px 18px; margin: 0 0 20px; }
.kicker { font-size: 12px; font-weight: 700; letter-spacing: .16em; color: var(--accent); text-transform: uppercase; }
h1 { font-size: 30px; line-height: 1.2; margin: 6px 0 8px; text-wrap: balance; }
.sub { color: var(--muted); font-size: 17px; margin: 0; }
.where, .note { background: var(--accent-soft); border-left: 4px solid var(--accent); padding: 12px 16px; margin: 0 0 20px; border-radius: 0 6px 6px 0; }
.note p:last-child { margin: 0; }
h2 { color: var(--accent); font-size: 21px; border-bottom: 1px solid var(--line); padding-bottom: 6px; margin: 34px 0 12px; }
h3 { font-size: 17px; margin: 22px 0 8px; }
h4 { font-size: 15px; margin: 16px 0 6px; }
p { margin: 0 0 10px; }
code { font-family: var(--mono); font-size: 13px; background: var(--code); padding: 1px 4px; border-radius: 3px; overflow-wrap: anywhere; }
ul, ol { margin: 0 0 12px; padding-left: 24px; }
li { margin-bottom: 4px; }
.tbl { overflow-x: auto; margin: 8px 0 16px; border: 1px solid var(--line); border-radius: 6px; }
table { width: 100%; border-collapse: collapse; font-size: 14px; background: var(--card); }
th { background: var(--accent); color: var(--accent-fg); text-align: left; padding: 8px 10px; font-weight: 700; position: sticky; top: 0; }
td { padding: 8px 10px; vertical-align: top; border-top: 1px solid var(--line); }
tbody tr:nth-child(even) td { background: var(--stripe); }
td ol, td ul { margin: 0; padding-left: 18px; }
.scen td.id { color: var(--accent); font-weight: 700; white-space: nowrap; }
.scen td.sc { min-width: 140px; }
.pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 0 6px; border-radius: 3px; line-height: 1.6; }
.pill.high { background: var(--high-soft); color: var(--high); }
.pill.med { background: var(--med-soft); color: var(--med); }
.pill.low, .tag { background: var(--new-soft); color: var(--new); }
.tag { display: inline-block; font-size: 11px; font-weight: 700; border-radius: 3px; padding: 0 6px; margin-left: 6px; vertical-align: 2px; }
.flow { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 8px 0 16px; }
.flow div { flex: 1 1 120px; border: 1px solid var(--line); border-radius: 5px; background: var(--card); padding: 8px; text-align: center; font-size: 13px; }
.flow span { color: var(--accent); }
.diagram { overflow-x: auto; margin: 10px 0 18px; padding: 14px; background: var(--card); border: 1px solid var(--line); border-radius: 6px; text-align: center; }
.diagram pre.mermaid { margin: 0; background: none; }
pre.code { font-family: var(--mono); font-size: 13px; background: var(--code); padding: 10px 12px; border-radius: 6px; overflow-x: auto; }
.bar { display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; align-items: center; margin: 0 0 18px; color: var(--muted); font-size: 14px; }
.bar a.btn { border: 1px solid var(--line); border-radius: 6px; padding: 5px 12px; text-decoration: none; font-weight: 600; background: var(--card); }
.pages { display: grid; gap: 18px; justify-items: center; }
.pages canvas { width: 100%; max-width: 900px; height: auto; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.18); border-radius: 2px; }
.loading { color: var(--muted); padding: 40px 0; text-align: center; }
.pager { display: flex; justify-content: space-between; gap: 12px; margin-top: 40px; padding-top: 16px; border-top: 1px solid var(--line); }
.pager a { text-decoration: none; font-weight: 600; }
.list { list-style: none; padding: 0; background: var(--card); border: 1px solid var(--line); border-radius: 8px; margin: 0 0 24px; }
.list li { margin: 0; border-top: 1px solid var(--line); }
.list li:first-child { border-top: 0; }
.list a { display: grid; grid-template-columns: 44px 1fr; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--fg); }
.list a:hover { background: var(--stripe); }
.list .num { font-family: var(--mono); font-weight: 700; color: var(--accent); }
.list small { display: block; color: var(--muted); }
.home h2 { font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); border: 0; margin: 24px 0 8px; padding: 0; }
@media (max-width: 860px) {
  .layout { grid-template-columns: 1fr; }
  nav.side { position: static; height: auto; border-right: 0; border-bottom: 1px solid var(--line); }
  nav.side .groups { display: none; }
  nav.side.open .groups { display: block; }
  .menu-btn { display: inline-block; margin: 0 8px; font: inherit; font-size: 14px; padding: 4px 10px; border: 1px solid var(--line); border-radius: 6px; background: var(--card); color: var(--fg); }
  main { padding: 20px 16px 48px; }
  h1 { font-size: 24px; }
}
@media print { nav.side, .bar, .pager { display: none; } .layout { display: block; } main { padding: 0; } }
`;
writeFileSync(join(OUT, 'assets/handbook.css'), CSS);

function nav(current) {
  let h = `<nav class="side" id="side"><a class="brand" href="index.html"><small>MW Realty</small><strong>Tester Handbook</strong></a><button class="menu-btn" type="button" onclick="document.getElementById('side').classList.toggle('open')">All guides</button><div class="groups">`;
  for (const g of GROUPS) {
    h += `<h4>${GROUP_LABEL[g]}</h4>`;
    for (const d of G.filter((x) => x.group === g)) {
      h += `<a class="item${d.file === current ? ' on' : ''}" href="${d.file}.html"><span>${d.n}</span>${esc(d.title)}</a>`;
    }
  }
  return h + '</div></nav>';
}
const page = (title, current, body) => `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>${esc(title)} · MW Realty Tester Handbook</title>
<link rel="stylesheet" href="assets/handbook.css"></head>
<body><div class="layout">${nav(current)}<main><div class="doc">${body}</div></main></div></body></html>`;

const seq = GROUPS.flatMap((g) => G.filter((x) => x.group === g)); // Previous / Next follow the sidebar order
function pager(d) {
  const i = seq.indexOf(d);
  const prev = seq[i - 1], next = seq[i + 1];
  return `<div class="pager">${prev ? `<a href="${prev.file}.html">&larr; ${prev.n} ${esc(prev.title)}</a>` : '<span></span>'}${next ? `<a href="${next.file}.html">${next.n} ${esc(next.title)} &rarr;</a>` : '<span></span>'}</div>`;
}

for (const d of G) {
  const pdf = `${d.file}.pdf`;
  const src = join(SCRATCH, 'html', `${d.file.replace(/^\d+-/, (m) => m)}.html`);
  const built = join(SCRATCH, 'html', `${d.n}-${d.file.slice(3)}.html`);
  let body;
  if (existsSync(built)) {
    // HTML guide: take the body built from the markdown source; wrap tables so they scroll on phones
    let b = readFileSync(built, 'utf8').split('<body>')[1].split('</body>')[0];
    b = b.replace(/<script[\s\S]*?<\/script>/g, '');
    b = b.replace(/<table/g, '<div class="tbl"><table').replace(/<\/table>/g, '</table></div>');
    const label = d.group === 'Project' ? `Project document ${d.n}` : `Module ${d.n}`;
    const mm = b.includes('class="mermaid"')
      ? `<script src="assets/mermaid.min.js"></script><script>mermaid.initialize({startOnLoad:true,theme:matchMedia('(prefers-color-scheme: dark)').matches?'dark':'neutral',flowchart:{useMaxWidth:true}});</script>`
      : '';
    body = `<div class="bar"><span>${label}</span><a class="btn" href="${pdf}" download>Download PDF</a></div>${b}${pager(d)}${mm}`;
  } else {
    body = `<div class="bar"><span>Module ${d.n} · ${esc(d.title)}</span><a class="btn" href="${pdf}" download>Download PDF</a></div>
<div class="pages" id="pages" data-src="${pdf}"><div class="loading">Loading the guide…</div></div>${pager(d)}
<script src="assets/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'assets/pdf.worker.min.js';
(async function () {
  const box = document.getElementById('pages');
  try {
    const doc = await pdfjsLib.getDocument(box.dataset.src).promise;
    box.innerHTML = '';
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const width = Math.min(box.clientWidth || 900, 900);
    for (let n = 1; n <= doc.numPages; n++) {
      const pg = await doc.getPage(n);
      const base = pg.getViewport({ scale: 1 });
      const vp = pg.getViewport({ scale: (width / base.width) * ratio });
      const c = document.createElement('canvas');
      c.width = vp.width; c.height = vp.height;
      c.setAttribute('aria-label', 'Page ' + n + ' of ' + doc.numPages);
      box.appendChild(c);
      await pg.render({ canvasContext: c.getContext('2d'), viewport: vp }).promise;
    }
  } catch (e) {
    box.innerHTML = '<div class="loading">This guide could not be shown. Use Download PDF above.</div>';
  }
})();
</script>`;
  }
  writeFileSync(join(OUT, `${d.file}.html`), page(d.title, d.file, body));
}

// Home page
let home = `<div class="home"><header class="title"><div class="kicker">MW Realty · Tester handbook</div><h1>MW Realty Tester Handbook</h1><p class="sub">Test guides for the public website, Admin and the partner CRM. Start with 00 (Project overview). The project documents P1–P8 cover requirements, specifications, access, test data, environment, APIs, batch jobs and automation. The module guides hold the detailed test scenarios.</p></header>`;
for (const g of GROUPS) {
  home += `<h2>${GROUP_LABEL[g]}</h2><ul class="list">`;
  for (const d of G.filter((x) => x.group === g)) {
    home += `<li><a href="${d.file}.html"><span class="num">${d.n}</span><span><strong>${esc(d.title)}</strong>${d.note ? `<small>${esc(d.note)}</small>` : ''}</span></a></li>`;
  }
  home += '</ul>';
}
home += `<p style="color:var(--muted);font-size:14px">Guides 24–26 were written from the code as of 2 November 2026. If a guide and the app disagree, report it to the team.</p></div>`;
writeFileSync(join(OUT, 'index.html'), page('Home', 'index', home));
console.log('pages:', G.length + 1);
