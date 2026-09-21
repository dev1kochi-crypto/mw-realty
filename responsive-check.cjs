const {chromium}=require('C:/Users/User/AppData/Local/npm-cache/_npx/e41f203b7505f1fb/node_modules/playwright-core');
const fs=require('fs');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:/Users/User/AppData/Local/ms-playwright/chromium-1243/chrome-win64/chrome.exe'});
 const page=await browser.newPage({reducedMotion:'reduce'});
 const routes=['/','/about','/contact','/agents','/agencies','/commercial','/properties-dubai','/property-details','/blogs','/login','/signup','/profile','/terms-and-conditions','/privacy-policy','/security-policy','/cookie-settings','/thank-you','/page-does-not-exist'];
 for(const [route,prefix] of [['/agents','/agent-details/'],['/agencies','/agency-details/'],['/blogs','/blog-details/']]){
  await page.goto('http://127.0.0.1:8000'+route,{waitUntil:'networkidle'});
  const href=await page.locator('main a[href^="'+prefix+'"]').first().getAttribute('href');
  routes.push(href);
 }
 const results=[];
 for(const width of [320,375,576,768,992,1280,1440,1920]){
  await page.setViewportSize({width,height:900});
  for(const route of routes){
   await page.goto('http://127.0.0.1:8000'+route,{waitUntil:'networkidle'});
   await page.addStyleTag({content:'[data-reveal]{transform:none!important;opacity:1!important} *,*::before,*::after{animation:none!important;transition:none!important}'});
   const issues=await page.evaluate(()=>[...document.querySelectorAll('main *,footer *')].filter(el=>{
    const r=el.getBoundingClientRect();
    if(!r.width||el.closest('.slick-slider,[class*=ticker],[class*=partners],[class*=modal],[class*=gallery],[class*=slides],.mw-dashboard__table-wrap'))return false;
    return el.clientWidth>0&&el.scrollWidth>el.clientWidth+2&&getComputedStyle(el).overflowX!=='auto';
   }).map(el=>({tag:el.tagName,cls:el.className,width:el.clientWidth,scrollWidth:el.scrollWidth,text:el.textContent.trim().slice(0,70)})));
   results.push({width,route,issues});
   if(issues.length)console.log(JSON.stringify({width,route,issues}));
  }
  console.log('Completed '+width+'px');
 }
 fs.writeFileSync('responsive-check-results.json',JSON.stringify(results,null,2));
 await browser.close();
})();
