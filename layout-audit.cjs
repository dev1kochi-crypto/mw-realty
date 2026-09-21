const { chromium } = require('C:/Users/User/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright-core');
const fs = require('fs');
(async () => {
 const browser = await chromium.launch({executablePath:'C:/Users/User/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe',headless:true});
 const page = await browser.newPage();
 const results=[];
 for (const width of [375,768,1280,1440,1920]) {
  await page.setViewportSize({width,height:900});
  for (const route of ['','about','contact','agents','agencies','commercial','properties-dubai','property-details','blogs','login','signup','profile','terms-and-conditions','thank-you']) {
   await page.goto('http://127.0.0.1:8000/'+route,{waitUntil:'networkidle'});
   const live=await inspect(page);
   if (process.env.COMPARE==='1') {
    await page.goto('http://127.0.0.1:8001/'+(route||'index')+'.php',{waitUntil:'networkidle'});
    const reference=await inspect(page);
    live.fontDifferences=Object.entries(live.fonts).filter(([key,value])=>reference.fonts[key]&&JSON.stringify(value)!==JSON.stringify(reference.fonts[key])).map(([key,value])=>({key,live:value,reference:reference.fonts[key]}));
   }
   results.push({width,route,...live});
   console.log(JSON.stringify({width,route,overflow:live.overflow,fontDifferences:live.fontDifferences,footer:live.footer}));
  }
 }
 fs.writeFileSync('layout-audit-results.json',JSON.stringify(results,null,2));
 await browser.close();
})();
async function inspect(page) {
 return page.evaluate(()=>{
  const fonts={};
  for(const el of document.querySelectorAll('h1,h2,h3,p,a,button,label,input')) {
   if(!el.className || !el.getBoundingClientRect().width) continue;
   const key=el.tagName+'.'+String(el.className).split(' ').filter(c=>!c.startsWith('router-')).join('.');
   const s=getComputedStyle(el);
   fonts[key]={size:s.fontSize,family:s.fontFamily,weight:s.fontWeight,lineHeight:s.lineHeight};
  }
  const overflow=[...document.querySelectorAll('main *,footer *')].filter(el=>{
   const r=el.getBoundingClientRect();
   return r.width>0&&(r.right>innerWidth+1||r.left< -1)&&!el.closest('.slick-slider,[class*=offcanvas],[class*=modal]');
  }).slice(0,12).map(el=>({tag:el.tagName,cls:el.className,text:el.textContent.trim().slice(0,50),width:Math.round(el.getBoundingClientRect().width)}));
  const footer={};
  for(const cls of ['grid','meta','contact-list','newsletter','bottom']){const r=document.querySelector('.mw-footer__'+cls).getBoundingClientRect();footer[cls]={x:Math.round(r.x),y:Math.round(r.y),width:Math.round(r.width),height:Math.round(r.height)}}
  return {fonts,overflow,footer};
 });
}
