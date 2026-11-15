const fs=require('fs');
const {chromium}=require(process.env.GC_PLAYWRIGHT_MODULE||'/opt/codex/cua_node/lib/node_modules/playwright-core');
const root=process.env.GC_PROJECT_ROOT||'/workspace/work/gold-commerce';
const results=[];
function assert(value,message){if(!value)throw new Error(message);}
function overlap(a,b){return a&&b&&a.x<b.x+b.width&&a.x+a.width>b.x&&a.y<b.y+b.height&&a.y+a.height>b.y;}
async function check(name,fn){const start=Date.now();try{await fn();results.push({test:name,status:'PASS',ms:Date.now()-start});}catch(error){results.push({test:name,status:'FAIL',error:error.message});}}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GC_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 for(const port of [8081,8082]){
  const context=await browser.newContext({viewport:{width:1440,height:700},colorScheme:'dark'});const page=await context.newPage();const errors=[];page.on('pageerror',error=>errors.push(error.message));const base='http://localhost:'+port;
  await check('VISITOR.'+port+'.light_default_and_persistence',async()=>{
   await page.goto(base,{waitUntil:'networkidle'});assert(await page.locator('html').getAttribute('data-theme')==='light','New visitor did not start in light mode');
   await page.locator('#gc-personalize-toggle').click();assert(await page.locator('#gc-personalize-panel').isVisible(),'Personalization panel did not open');await page.locator('#gc-theme-toggle').click();assert(await page.locator('html').getAttribute('data-theme')==='dark','Dark theme did not apply');
   await page.reload({waitUntil:'networkidle'});assert(await page.locator('html').getAttribute('data-theme')==='dark','Theme preference did not persist');await page.locator('#gc-personalize-toggle').click();await page.locator('#gc-personalize-reset').click();assert(await page.locator('html').getAttribute('data-theme')==='light','Reset did not restore light mode');
  });
  await check('VISITOR.'+port+'.personalization_controls',async()=>{
   await page.locator('#gc-text-plus').click();assert(await page.locator('html').evaluate(el=>parseFloat(el.style.fontSize)===105),'Text size control failed');await page.locator('[data-accent="#315B4A"]').click();assert((await page.locator('html').getAttribute('style')||'').includes('--accent'),'Accent control failed');await page.locator('[data-font-value="system"]').click();assert(await page.locator('html').evaluate(el=>el.classList.contains('gc-system-font')),'Font control failed');await page.locator('#gc-personalize-reset').click();await page.keyboard.press('Escape');
  });
  await check('VISITOR.'+port+'.accessibility_controls',async()=>{
   await page.locator('#gc-access-toggle').click();for(const value of ['contrast','spacing','motion','font'])await page.locator('[data-a11y-value="'+value+'"]').click();const active=await page.locator('html').getAttribute('data-a11y')||'';for(const value of ['contrast','spacing','motion','font'])assert(active.includes(value),'Accessibility option missing: '+value);await page.reload({waitUntil:'networkidle'});assert((await page.locator('html').getAttribute('data-a11y')||'').includes('contrast'),'Accessibility preferences did not persist');await page.locator('#gc-access-toggle').click();await page.locator('#gc-access-reset').click();await page.keyboard.press('Escape');
  });
  for(const width of [1440,360])await check('VISITOR.'+port+'.no_overlap_'+width,async()=>{
   await page.setViewportSize({width,height:520});await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await page.waitForTimeout(350);const selectors=['#gc-personalize-toggle','#gc-access-toggle','#gc-back-to-top'];const boxes=await Promise.all(selectors.map(selector=>page.locator(selector).boundingBox()));assert(boxes.every(Boolean),'A floating control is not visible');assert(!overlap(boxes[0],boxes[1])&&!overlap(boxes[0],boxes[2])&&!overlap(boxes[1],boxes[2]),'Floating controls overlap at '+width+'px');
  });
  await check('VISITOR.'+port+'.javascript_errors',async()=>assert(!errors.length,'JavaScript errors: '+errors.join('; ')));await context.close();
 }
 await browser.close();fs.writeFileSync(root+'/evidence/visitor-controls.json',JSON.stringify({at_utc:new Date().toISOString(),results},null,2));console.log(JSON.stringify(results,null,2));process.exit(results.some(result=>result.status==='FAIL')?1:0);
})();
