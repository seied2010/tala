const fs=require('fs'),path=require('path');
const {chromium}=require('/opt/codex/cua_node/lib/node_modules/playwright-core');
const AxeBuilder=require('../tools/a11y/node_modules/@axe-core/playwright').default;
(async()=>{const browser=await chromium.launch({executablePath:'/usr/bin/chromium',headless:true,args:['--no-sandbox']});const results=[];
for(const width of [1440,360]){const context=await browser.newContext({viewport:{width,height:900}});const page=await context.newPage();
for(const [name,url] of [['home','/'],['product','/?post_type=product&p=18'],['cart','/?add-to-cart=18'],['checkout','/?page_id=7']]){
await page.goto('http://localhost:8081'+url,{waitUntil:'networkidle'});if(name==='cart')await page.goto('http://localhost:8081/?page_id=6',{waitUntil:'networkidle'});
for(const mode of ['light','dark']){await page.evaluate(theme=>document.documentElement.dataset.theme=theme,mode);const report=await new AxeBuilder({page}).withTags(['wcag2a','wcag2aa','wcag21aa','wcag22aa']).analyze();results.push({page:name,width,mode,status:report.violations.length?'FAIL':'PASS',violations:report.violations.map(v=>({id:v.id,impact:v.impact,description:v.description,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))});}
}await context.close();}await browser.close();fs.writeFileSync(path.join(__dirname,'../evidence/accessibility.json'),JSON.stringify({at_utc:new Date().toISOString(),engine:'axe-core',results},null,2));console.log(JSON.stringify(results,null,2));process.exit(results.some(r=>r.status==='FAIL')?1:0);})();
