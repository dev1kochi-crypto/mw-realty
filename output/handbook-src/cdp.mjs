// Usage: node cdp.mjs <url> <out> [pdf|png] [headerHtml] [footerHtml]
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const [url, out, mode = 'pdf', header = '<span></span>', footer = '<span></span>'] = process.argv.slice(2);
const port = 9300 + Math.floor(Math.random() * 500);
const chrome = spawn('C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', '--disable-gpu', `--remote-debugging-port=${port}`,
  `--user-data-dir=${mkdtempSync(join(tmpdir(), 'cdp-'))}`, '--window-size=900,1400', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let target;
for (let i = 0; i < 50 && !target; i++) {
  await sleep(200);
  try {
    const list = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    target = list.find((t) => t.type === 'page');
  } catch {}
}
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0;
const pending = new Map();
ws.addEventListener('message', (e) => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); }
});
const send = (method, params = {}) => new Promise((r) => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });

await send('Page.enable');
await send('Page.navigate', { url });
for (let i = 0; i < 150; i++) {
  await sleep(200);
  const r = await send('Runtime.evaluate', { expression: 'document.readyState + "|" + document.title' });
  const v = r.result?.result?.value || '';
  if (v.startsWith('complete') && (!url.includes('view.html') || v.endsWith('done'))) break;
}
// pages with diagrams flag body[data-ready] once mermaid has drawn them
for (let i = 0; i < 75; i++) {
  const r = await send('Runtime.evaluate', { expression: '!!document.querySelector(".mermaid") && !(document.body.dataset.ready)' });
  if (!r.result?.result?.value) break;
  await sleep(200);
}
await sleep(500);
if (mode === 'eval') {
  const r = await send('Runtime.evaluate', { expression: process.env.EXPR });
  console.log(r.result?.result?.value);
} else if (mode === 'png') {
  const m = await send('Runtime.evaluate', { expression: 'JSON.stringify([document.body.scrollWidth, document.body.scrollHeight])' });
  let [w, h] = JSON.parse(m.result.result.value); if (process.env.MAXH) h = Math.min(h, +process.env.MAXH);
  const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width: w, height: h, scale: 1 } });
  writeFileSync(out, Buffer.from(r.result.data, 'base64'));
} else {
  const r = await send('Page.printToPDF', {
    printBackground: true, preferCSSPageSize: true, displayHeaderFooter: true,
    headerTemplate: header, footerTemplate: footer,
  });
  writeFileSync(out, Buffer.from(r.result.data, 'base64'));
}
ws.close();
chrome.kill();
console.log('wrote', out);
