const fs=require('fs');
const {chromium}=require(process.env.GC_PLAYWRIGHT_MODULE||'/opt/codex/cua_node/lib/node_modules/playwright-core');
const root=process.env.GC_PROJECT_ROOT||'/workspace/work/gold-commerce';
const results=[];const errors=[];
async function check(name,fn){const start=Date.now();try{await fn();results.push({test:name,status:'PASS',ms:Date.now()-start});}catch(e){results.push({test:name,status:'FAIL',error:e.message});}}
function assert(x,message){if(!x)throw new Error(message);}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GC_CHROMIUM||'/usr/bin/chromium',headless:true,args:['--no-sandbox']});
 for(const port of [8081,8082]){
  const context=await browser.newContext({viewport:{width:1440,height:1000}});const page=await context.newPage();page.on('pageerror',e=>errors.push({port,error:e.message}));
  const base='http://localhost:'+port;
  await check('T39.'+port+'.rtl_responsive_dark',async()=>{
   await page.goto(base,{waitUntil:'networkidle'});assert(await page.locator('html').getAttribute('dir')==='rtl','RTL missing');assert(await page.locator('html').getAttribute('data-theme')==='light','Default theme is not light');
   await page.screenshot({path:root+'/evidence/home-'+port+'-desktop.png',fullPage:true});
   await page.locator('#gc-personalize-toggle').click();await page.locator('#gc-theme-toggle').click();assert(await page.locator('html').getAttribute('data-theme')==='dark','Dark mode failed');
   await page.setViewportSize({width:360,height:800});await page.screenshot({path:root+'/evidence/home-'+port+'-mobile-dark.png',fullPage:true});
   assert(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Horizontal overflow at 360px');
   await page.locator('#gc-menu-toggle').click();assert(await page.locator('#gc-menu-toggle').getAttribute('aria-expanded')==='true','Menu inaccessible');
   await page.locator('#gc-personalize-toggle').click();await page.locator('#gc-theme-light').click();await page.setViewportSize({width:1440,height:1000});
  });
  await check('T40.'+port+'.keyboard_and_controls',async()=>{
   await page.goto(base,{waitUntil:'networkidle'});await page.keyboard.press('Tab');assert(await page.evaluate(()=>document.activeElement.classList.contains('gc-skip')),'Skip link not first');
   await page.keyboard.press('Enter');await page.locator('#gc-access-toggle').click();assert(await page.locator('#gc-access-panel').isVisible(),'Accessibility controls hidden');
   await page.locator('#gc-contrast').click();assert((await page.locator('html').getAttribute('data-a11y')||'').includes('contrast'),'High contrast failed');await page.locator('#gc-access-reset').click();
   await page.keyboard.press('Escape');assert(!await page.locator('#gc-access-panel').isVisible(),'Escape failed');
   await page.locator('#gc-personalize-toggle').click();await page.locator('#gc-text-plus').click();assert(await page.locator('html').evaluate(e=>parseFloat(e.style.fontSize)>100),'Text size failed');await page.locator('#gc-personalize-reset').click();await page.keyboard.press('Escape');
   await page.evaluate(()=>window.scrollTo(0,document.body.scrollHeight));await page.waitForTimeout(300);const boxes=await Promise.all(['#gc-personalize-toggle','#gc-access-toggle','#gc-back-to-top'].map(s=>page.locator(s).boundingBox()));const overlap=(a,b)=>a&&b&&a.x<b.x+b.width&&a.x+a.width>b.x&&a.y<b.y+b.height&&a.y+a.height>b.y;assert(!overlap(boxes[0],boxes[1])&&!overlap(boxes[0],boxes[2])&&!overlap(boxes[1],boxes[2]),'Floating controls overlap');
  });
  await check('T37.'+port+'.classic_checkout_end_to_end',async()=>{
   await page.goto(base+'/?post_type=product&p=18',{waitUntil:'networkidle'});assert(await page.locator('button.single_add_to_cart_button').count()===1,'No buy action');
   await page.locator('button.single_add_to_cart_button').click();await page.waitForLoadState('networkidle');
   await page.goto(base+'/?page_id=7',{waitUntil:'networkidle'});assert(await page.locator('form.checkout').count()===1,'Classic checkout not installed');
   await page.locator('#billing_first_name').fill('آزمایش');await page.locator('#billing_last_name').fill('فروشگاه');await page.locator('#billing_country').selectOption('IR');
   await page.locator('#billing_address_1').fill('نشانی ساختگی شماره ۱');await page.locator('#billing_city').fill('تهران');await page.locator('#billing_state').selectOption('THR');await page.locator('#billing_postcode').fill('1111111111');await page.locator('#billing_phone').fill('09120000000');await page.locator('#billing_email').fill('browser'+port+'@example.test');
   const shipping=page.locator('#ship-to-different-address-checkbox');if(await shipping.count()&&await shipping.isChecked())await shipping.uncheck();
   await page.waitForTimeout(1800);const gateway=page.locator('#payment_method_gc_test');assert(await gateway.count()===1,'Test gateway not available');if(!await gateway.isChecked())await gateway.check();
   const terms=page.locator('#terms');if(await terms.count())await terms.check();
   await page.screenshot({path:root+'/evidence/checkout-'+port+'.png',fullPage:true});
   await page.locator('#place_order').click();await page.waitForURL(/order-received/, {timeout:30000});await page.waitForLoadState('networkidle');
   assert(await page.locator('.woocommerce-order-overview__order').count()===1,'Order confirmation missing');
   await page.screenshot({path:root+'/evidence/order-paid-'+port+'.png',fullPage:true});
  });
  await check('T42.'+port+'.private_cache_headers',async()=>{
   for(const id of [6,7,8,11]){const response=await page.goto(base+'/?page_id='+id,{waitUntil:'domcontentloaded'});const h=response.headers();assert(/no-store|no-cache/.test(h['cache-control']||''),'Cache header missing '+id);assert((h['x-robots-tag']||'').includes('noindex'),'Noindex missing '+id);}
  });
  await check('T41.'+port+'.yoast_canonical_and_private_noindex',async()=>{
   await page.goto(base+'/?post_type=product&p=18',{waitUntil:'networkidle'});assert(await page.locator('link[rel="canonical"]').count()===1,'Duplicate/missing canonical');
   const graphs=await page.locator('script[type="application/ld+json"]').allTextContents();const all=graphs.map(x=>JSON.parse(x));let offers=[];for(const data of all){const list=data['@graph']||[data];for(const node of list)if(node['@type']==='Product'&&node.offers)offers.push(...(Array.isArray(node.offers)?node.offers:[node.offers]));}assert(offers.length===1,'Expected one Product offer');assert(String(offers[0].price)==='28073800','Schema price differs from displayed server price');
  });
  await context.close();
 }
 await browser.close();
 results.push({test:'BROWSER.javascript_errors',status:errors.length?'FAIL':'PASS',errors});fs.writeFileSync(root+'/evidence/browser.json',JSON.stringify({at_utc:new Date().toISOString(),results},null,2));console.log(JSON.stringify(results,null,2));process.exit(results.some(x=>x.status==='FAIL')?1:0);
})();
