// Splits guides 27 (website pages) and 26 (compare, insights, chat) into one guide per website menu area (W1–W5).
import { readFileSync, writeFileSync } from 'node:fs';

const DIR = 'C:/Users/User/AppData/Local/Temp/claude/c--Laravel-mw-realty/e2285a8f-4858-45a7-83e5-c35fa6db4434/scratchpad/guides/';
const A = readFileSync(DIR + '27-website-pages.md', 'utf8').replace(/\r/g, '');
const B = readFileSync(DIR + '26-website-compare-insights-chat.md', 'utf8').replace(/\r/g, '');

// --- parsing helpers -------------------------------------------------------
function sections(md) {
  const out = {};
  const parts = md.split(/\n(?=## \d+\. )/);
  for (const p of parts.slice(1)) out[p.match(/^## (\d+)\./)[1]] = p.replace(/^## .*\n/, '');
  return out;
}
const subsections = (body) => body.split(/\n(?=### )/).filter((x) => x.startsWith('### '));
// blocks = paragraphs, tables, bullet items (with their nested lines); keeps order
function blocks(body) {
  const lines = body.split('\n');
  const out = [];
  let i = 0;
  while (i < lines.length) {
    const l = lines[i];
    if (!l.trim()) { i++; continue; }
    if (l.startsWith('|')) {
      const t = [];
      while (i < lines.length && lines[i].startsWith('|')) t.push(lines[i++]);
      out.push({ type: 'table', head: t.slice(0, 2), rows: t.slice(2) });
    } else if (/^- /.test(l)) {
      const b = [l];
      i++;
      while (i < lines.length && /^\s{2,}\S/.test(lines[i])) b.push(lines[i++]);
      out.push({ type: 'bullet', text: b.join('\n') });
    } else {
      const p = [];
      while (i < lines.length && lines[i].trim() && !lines[i].startsWith('|') && !/^- /.test(lines[i])) p.push(lines[i++]);
      out.push({ type: 'para', text: p.join('\n') });
    }
  }
  return out;
}

const SA = sections(A), SB = sections(B);
const subA = Object.fromEntries(subsections(SA['2']).map((s) => [s.match(/^### (2\.\d+)/)[1], s]));
const subB = Object.fromEntries(subsections(SB['2']).map((s) => [s.match(/^### (2\.\d+)/)[1], s]));

// --- page definitions ------------------------------------------------------
const PAGES = [
  {
    id: 'W1', file: 'W1-home', title: 'Website: Home, Header & Account',
    sub: 'The home page, the header and footer on every page, the AI chat, language and currency, and customer sign-up, login and My Account.',
    where: '**Where:** Public website: Home (/), the header, footer and mobile bottom bar on every page, the AI chat widget (bottom corner, every page), /signup, /login, /verify-email, /forgot-password, /reset-password and /profile. **Who:** Visitors, Customers (website "User" accounts); agencies and agents sign in at the same /login. **Locks:** None on browsing; wishlist, Save Search and /profile need a Customer sign-in. The AI chat needs an AI provider configured on the server.',
    intro: 'Home shows Admin-managed sections and live listings. The header and footer appear on every page and hold the menu, language and currency switchers and the account menu. The AI chat answers visitors in 10 languages and turns them into leads. Customers sign up with an email code and manage their wishlist, saved searches and enquiries in My Account.',
    subsA: ['2.1', '2.2', '2.10'], subsB: ['2.4', '2.3', '2.5'],
    prefixes: ['HD', 'HM', 'AU', 'CH', 'CU', 'OT'],
    kw: /header|footer|home|banner|newsletter|get in touch|language|currency|rates?\b|chat|noor|sign[- ]?up|log ?in|verify|password|profile|my account|wishlist|save search|saved search|account settings|right-to-left|rtl|seo|sitemap|tracking|cookie|ticker|comming/i,
  },
  {
    id: 'W2', file: 'W2-buy-rent-commercial', title: 'Website: Buy, Rent & Commercial',
    sub: 'Property search for sale, rent and commercial, map views, Premium and Realty lists, the property details page, Compare and the market insight tabs.',
    where: '**Where:** /properties?listing_type=sale (Buy), /properties?listing_type=rent (Rent), /commercial, the /map views, /premium-properties, /marketing-properties, /property-details/{slug}, the floating compare bar and /compare. **Who:** Everyone; no sign-in needed except for the wishlist and Save Search. **Locks:** Only live listings appear. The AI summary on /compare needs an AI provider on the server.',
    intro: 'Buy and Rent are the residential search; Commercial has its own filter set. Each list has a map view. The property page shows the listing, its permit, nearby places and contact forms, which create leads in the CRM. Visitors can compare up to 5 listings side by side, and the property page shows mortgage, investment, rental-cost and market insight tabs.',
    subsA: ['2.3', '2.4'], subsB: ['2.1', '2.2', '2.6'],
    prefixes: ['PS', 'PD', 'CP', 'IN'],
    kw: /propert(y|ies)|listing|filter|map|commercial|buy|rent\b|rent |rental|premium|realty|compare|insight|yield|mortgage|investment|viewing|enquiry|brochure|floor[- ]plan|similar|permit|card|save search|wishlist|sort|pag(e|ing)/i,
  },
  {
    id: 'W3', file: 'W3-agents-agencies', title: 'Website: Agents & Agencies',
    sub: 'The agent and agency directories, their public profiles, and the Custom Property Request form that sends a lead to the agent or agency.',
    where: '**Where:** /agents, /agent-details/{slug}, /agencies, /agency-details/{slug}. **Who:** Everyone; no sign-in needed. **Locks:** Only approved partner accounts are listed.',
    intro: 'The directories list approved agents and agencies with a search box. Each profile shows the partner\'s details and live listings, and has a Custom Property Request ("find me a property") form that creates a lead in that agent\'s or agency\'s CRM.',
    subsA: ['2.5', '2.6'], subsB: [],
    prefixes: ['AG', 'AC'],
    kw: /agent|agenc|custom property request|profile request|serves in/i,
  },
  {
    id: 'W4', file: 'W4-calculators', title: 'Website: Calculators',
    sub: 'The mortgage, eligibility, rent-vs-buy and refinance calculators: inputs, formulas, worked examples, unlock popups and enquiry forms.',
    where: '**Where:** Calculators menu: /mortgage-calculator, /eligibility-calculator, /rent-vs-buy-calculator, /refinance-calculator. **Who:** Everyone; no sign-in needed. **Locks:** Eligibility and Refinance results unlock after the visitor enters contact details.',
    intro: 'Each calculator runs in the browser and is meant to give the same figures as Property Finder\'s calculators for the same inputs. Copy, FAQs and enquiry-form text come from Admin > Calculators. Enquiries go to Admin > Enquiries with the figures in AED.',
    subsA: ['2.8'], subsB: [],
    prefixes: ['CA'],
    kw: /calculat|mortgage quote|eligibility|refinance|rent[- ]vs[- ]buy|unlock/i,
  },
  {
    id: 'W5', file: 'W5-explore', title: 'Website: Explore & Market Insights',
    sub: 'Market Insights reports and the Explore menu: About, Contact, Blog and Careers, plus legal pages, the thank-you page, landing pages and the 404 page.',
    where: '**Where:** /market-insights, /market-insights/{slug}, Explore menu: /about, /contact, /blogs, /blog-details/{slug}, /careers, /careers/{slug}; legal pages /terms-and-conditions, /privacy-policy, /security-policy, /cookie-settings; /thank-you; landing pages /{slug}; any unknown URL (404). **Who:** Everyone. **Locks:** None.',
    intro: 'These pages show content managed in Admin. The Contact form goes to Admin > Enquiries with an email to the admin; career applications go to Admin > Careers > Candidates.',
    subsA: ['2.7', '2.9'], subsB: [],
    prefixes: ['MI', 'EX'],
    kw: /market insight|explore|about|contact|blog|career|cv\b|legal|terms|privacy|cookie|thank-you|landing|404|not found|share icon/i,
  },
];
const W_REF = { W2: 'W2 (Buy, Rent & Commercial)', W1: 'W1 (Home, Header & Account)' };

// --- build each page --------------------------------------------------------
const rowText = (r) => r.replace(/<br>/g, ' ');
function pick(items, page, fallbackPage, text = (x) => x) {
  return items.filter((it) => {
    const t = text(it);
    const hits = PAGES.filter((p) => p.kw.test(t));
    return hits.length ? hits.includes(page) : page.id === fallbackPage;
  });
}
// single best page for a bullet: most keyword hits (first page wins a tie)
// bullets whose wording misleads the keyword match
const PIN = [
  [/^- Header order/, "W1"], [/^- Every Home section/, "W1"], [/^- Calculators reproduce/, "W4"], [/^- No console errors and no sideways/, "W1"],
  [/^- The summary shows \*\*AI summary/, "W2"], [/^- Currency choice converts every amount; the FX margin/, "W2"],
  [/^- Some texts are built-in English/, "W1"], [/^- The ticker/, "W1"], [/^- The "Properties : N"/, "W3"],
  [/^- Home content is cached/, "W1"], [/^- \*\*Compare\*\* texts/, "W2"], [/^- The Custom Property Request is no longer/, "W3"],
];
function best(items, page, fb) {
  return items.filter((it) => {
    const pin = PIN.find(([re]) => re.test(it.text));
    if (pin) return page.id === pin[1];
    const scores = PAGES.map((p) => (it.text.match(new RegExp(p.kw.source, "gi")) || []).length);
    const max = Math.max(...scores);
    return max ? PAGES[scores.indexOf(max)] === page : page.id === fb;
  });
}
function fixRefs(s, page) {
  return s
    .replace(/\b(covered in|see|in) Module 26\b/gi, (m, w) => `${w} ${page.id === 'W2' ? 'W1 (AI chat and currency)' : W_REF.W2}`)
    .replace(/\bModule 26\b/g, page.id === 'W2' ? 'W1' : 'W2')
    .replace(/(Module 00)/g, '(guide 00)').replace(/Module 00/g, 'guide 00');
}

for (const page of PAGES) {
  let md = `# ${page.title}\n\n${page.sub}\n\n${page.where}\n\n## 1. What this page covers\n\n${page.intro}\n\n`;

  // section 1 table rows from 27
  const t1 = blocks(SA['1']).find((b) => b.type === 'table');
  const rows1 = pick(t1.rows, page, 'W1', (r) => r.split('|')[1] + ' ' + r.split('|')[2]);
  md += [...t1.head, ...rows1].join('\n') + '\n\n';

  // section 2: chosen subsections, renumbered
  md += '## 2. How it works (business rules)\n\n';
  let n = 0;
  for (const s of [...page.subsA.map((k) => subA[k]), ...page.subsB.map((k) => subB[k])]) {
    n++;
    md += s.replace(/^### 2\.\d+ /, `### 2.${n} `).trimEnd() + '\n\n';
  }

  // section 3: table rows from both guides
  md += '## 3. Who can do what\n\n';
  const t3a = blocks(SA['3']).find((b) => b.type === 'table');
  md += [...t3a.head, ...pick(t3a.rows, page, 'W1', rowText)].join('\n') + '\n\n';
  if (page.subsB.length) {
    const t3b = blocks(SB['3']).find((b) => b.type === 'table');
    const r = pick(t3b.rows, page, 'W2', rowText);
    if (r.length) md += [...t3b.head, ...r].join('\n') + '\n\n';
  }

  // section 4: shared rules + forms of this page (+ rate limits)
  md += '## 4. Fields and validation\n\n';
  const b4 = blocks(SA['4']);
  const shared = b4.slice(0, b4.findIndex((b) => b.type === 'table'));
  md += shared.map((b) => b.text).join('\n') + '\n\n';
  const t4 = b4.find((b) => b.type === 'table');
  const forms = pick(t4.rows, page, 'W1', (r) => r.split('|')[1]);
  if (forms.length) md += [...t4.head, ...forms].join('\n') + '\n\n';
  const after = b4.slice(b4.indexOf(t4) + 1);
  md += after.map((b) => (b.type === 'table' ? [...b.head, ...b.rows].join('\n') : b.text)).join('\n') + '\n\n';
  if (page.subsB.length) {
    const b4b = blocks(SB['4']);
    const keep = b4b.filter((b) => b.type !== 'table' && pick([b.text], page, 'W2').length);
    const tabs = b4b.filter((b) => b.type === 'table').map((t) => ({ ...t, rows: pick(t.rows, page, 'W2', rowText) })).filter((t) => t.rows.length);
    if (keep.length || tabs.length) md += keep.map((b) => b.text).join('\n') + '\n\n' + tabs.map((t) => [...t.head, ...t.rows].join('\n')).join('\n\n') + '\n\n';
  }

  // section 5: scenarios by ID prefix from both guides
  md += '## 5. Test scenarios\n\nPriority: **High** core flow / data loss / access · **Medium** · **Low** cosmetic. Use fake names and example.com emails.\n\n';
  const scen = [SA['5'], SB['5']].flatMap((s) => blocks(s).filter((b) => b.type === 'table'));
  const head5 = scen[0].head;
  const rows5 = scen.flatMap((t) => t.rows).filter((r) => page.prefixes.includes(r.split('|')[1].trim().split('-')[0]));
  md += [...head5, ...rows5].join('\n') + '\n\n';

  // sections 6–7: bullets matched by keywords
  for (const [num, title, fb] of [['6', 'Expected results checklist', 'W1'], ['7', 'Known behaviour (not bugs)', 'W1']]) {
    md += `## ${num}. ${title}\n\n`;
    const items = [...blocks(SA[num]), ...(page.subsB.length ? blocks(SB[num]) : [])].filter((b) => b.type === 'bullet');
    const caution = items.filter((b) => /test data cautions/i.test(b.text));
    const normal = items.filter((b) => !/test data cautions/i.test(b.text));
    const mine = best(normal, page, fb);
    md += [...mine, ...caution.slice(0, 1)].map((b) => b.text).join('\n') + '\n\n';
  }

  md += '## 8. Reporting a bug\n\n' + SA['8'].trim() + '\n';
  // section numbers changed with the split
  const RENUM = { W2: [['section 2.2', 'section 2.4']], W4: [['section 2.8', 'section 2.1']], W5: [['section 2.9', 'section 2.2']] };
  for (const [from, to] of RENUM[page.id] || []) md = md.split(from).join(to);
  writeFileSync(DIR + page.file + '.md', fixRefs(md, page));
  console.log(page.file, 'scenarios:', rows5.length);
}
