const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const asset = name => fs.readFileSync(path.join(__dirname, '../../../assets/js/', name + '.js'), 'utf8');

async function boot(page, reload = false) {
 await page.route('https://audit.example/**', r => r.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head></head><body></body></html>' }));
 await page.goto('https://audit.example/');
 await page.evaluate(reload => {
  window.cmpConfig = { cookieName: 'cmp_consent', restUrl: 'https://audit.example/consent', configVersion: 2, expiryDays: 180, reloadOnWithdrawal: reload,
   categories: [{slug: 'analytics', required: false}],
   services: { tracker: { category: 'analytics', cookies: ['_tracker'], storageKeys: [] }, other: { category: 'analytics', cookies: [], storageKeys: [] } } };
 }, reload);
 for (const name of ['consent', 'cleanup', 'blocking']) await page.addScriptTag({content: asset(name)});
}

test('inherited permission withdrawal cleans only the revoked service once', async ({page}) => {
 await boot(page);
 const result = await page.evaluate(() => {
  window.calls = { tracker: 0, other: 0 };
  CMP.registerCleanup('tracker', () => calls.tracker++);
  CMP.registerCleanup('other', () => calls.other++);
  CMP.setConsent({analytics: true}, {});
  document.cookie = '_tracker=value;path=/';
  CMP.setConsent({analytics: true}, {tracker: false});
  return {calls, cookie: document.cookie.includes('_tracker=')};
 });
 expect(result).toEqual({calls: {tracker: 1, other: 0}, cookie: false});
});

test('category withdrawal preserves an explicitly retained service', async ({page}) => {
 await boot(page);
 const result = await page.evaluate(() => {
  let calls = 0; CMP.registerCleanup('tracker', () => calls++);
  CMP.acceptAll(); CMP.setConsent({analytics: false}, {tracker: true, other: false});
  return {calls, granted: CMP.hasServiceConsent('tracker')};
 });
 expect(result).toEqual({calls: 0, granted: true});
});

test('external library loads before its inline initializer', async ({page}) => {
 await boot(page);
 await page.route('https://tracker.example/library.js', async r => {
  await new Promise(resolve => setTimeout(resolve, 100));
  await r.fulfill({contentType:'application/javascript', body:'window.libraryReady=true;window.order.push("library")'});
 });
 await page.evaluate(() => {
  window.order=[];
  document.body.innerHTML='<script type="text/plain" data-cmp-category="analytics" src="https://tracker.example/library.js"></script><script type="text/plain" data-cmp-category="analytics">window.order.push("initializer:"+!!window.libraryReady)</script>';
  CMP.acceptAll();
 });
 await expect.poll(() => page.evaluate(() => window.order)).toEqual(['library', 'initializer:true']);
});

test('failed external library does not run dependent initializer', async ({page}) => {
 await boot(page);
 await page.route('https://tracker.example/broken.js', r => r.abort());
 await page.evaluate(() => {
  document.addEventListener('cmp:script-error', () => window.failed=true);
  document.body.innerHTML='<script type="text/plain" data-cmp-category="analytics" src="https://tracker.example/broken.js"></script><script type="text/plain" data-cmp-category="analytics">window.initialized=true</script>';
  CMP.acceptAll();
 });
 await expect.poll(() => page.evaluate(() => !!window.failed)).toBe(true);
 expect(await page.evaluate(() => !!window.initialized)).toBe(false);
});

test('withdrawal reblocks an iframe and its responsive image', async ({page}) => {
 await boot(page);
 const result=await page.evaluate(() => {
  document.body.innerHTML='<iframe data-cmp-src="about:blank" data-cmp-service="tracker"></iframe><img data-cmp-src="/image" data-cmp-srcset="/image2 2x" data-cmp-service="tracker">';
  CMP.acceptAll(); CMP.rejectAll();
  return Array.from(document.querySelectorAll('iframe,img')).map(el => [el.getAttribute('src'),el.getAttribute('srcset'),el.hasAttribute('data-cmp-released')]);
 });
 expect(result).toEqual([[null,null,false],[null,null,false]]);
});

test('stale decision cannot be resurrected by enabling another service', async ({page}) => {
 await boot(page);
 await page.evaluate(() => document.cookie='cmp_consent='+encodeURIComponent(JSON.stringify({consent_id:'test',config_version:1,expiry:9999999999,categories:{analytics:true},services:{tracker:true}}))+';path=/');
 await page.addScriptTag({content: asset('consent')});
 expect(await page.evaluate(() => {CMP.enableService('other');CMP.enableService('unknown');return [CMP.hasServiceConsent('tracker'),CMP.hasServiceConsent('other'),CMP.hasServiceConsent('unknown')]})).toEqual([false,true,false]);
});

test('reload follows cleanup and consent POST dispatch', async ({page}) => {
 await boot(page,true);
 await page.evaluate(() => {
  window.fetch = () => { sessionStorage.setItem('posted','yes'); return Promise.resolve(); };
  CMP.registerCleanup('tracker', () => sessionStorage.setItem('cleaned','yes'));
  document.body.innerHTML='<script type="text/plain" data-cmp-service="tracker">window.executed=true</script>';
  CMP.acceptAll();
 });
 await expect.poll(() => page.evaluate(() => !!window.executed)).toBe(true);
 const navigation=page.waitForEvent('load');
 await page.evaluate(() => CMP.rejectAll());
 await navigation;
 expect(await page.evaluate(() => [sessionStorage.getItem('cleaned'),sessionStorage.getItem('posted')])).toEqual(['yes','yes']);
});

test('rewritten real markup makes no tracker requests before consent', async ({page}) => {
 const {execFileSync} = require('child_process');
 const markup = '<script src=https://tracker.example/unquoted.js></script>' +
  '<script data-src="/local.js" src="https://tracker.example/exact.js"></script>' +
  '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" srcset="https://tracker.example/pixel.png 1x">' +
  '<img srcset="https://tracker.example/only.png 1x">';
 const rewritten=execFileSync('php', ['-r', 'require $argv[1]; $r=new Consentimental\\Services\\ServiceRegistry(new Consentimental\\Services\\CustomServiceRepository()); $r->register(["id"=>"tracker","category"=>"analytics","domains"=>["tracker.example"]]); echo (new Consentimental\\Blocking\\DomRewriter($r))->rewrite($argv[2]);', path.join(__dirname,'../../bootstrap.php'),markup], {encoding:'utf8'});
 const requests=[];
 await page.route('https://tracker.example/**', r => {requests.push(r.request().url());return r.fulfill({contentType:r.request().url().endsWith('.js')?'application/javascript':'image/gif',body:''});});
 const config={cookieName:'cmp_consent',configVersion:1,expiryDays:180,categories:[{slug:'analytics',required:false}],services:{tracker:{category:'analytics'}}};
 await page.route('https://audit.example/**', r=>r.fulfill({contentType:'text/html',body:'<!doctype html><html><head><script>var cmpConfig='+JSON.stringify(config)+';'+asset('consent')+'</script></head><body>'+rewritten+'<script>'+asset('blocking')+'</script></body></html>'}));
 await page.goto('https://audit.example/');
 expect(requests).toEqual([]);
 await page.evaluate(()=>CMP.acceptAll());
 await expect.poll(()=>requests.length).toBe(4);
});

test('module execution completes before the following initializer', async ({page}) => {
 await boot(page);
 await page.evaluate(()=>{
  window.order=[];
  document.body.innerHTML='<script type="text/plain" data-cmp-original-type="module" data-cmp-category="analytics">await new Promise(r=>setTimeout(r,50));window.order.push("module")</script><script type="text/plain" data-cmp-category="analytics">window.order.push("initializer")</script>';
  CMP.acceptAll();
 });
 await expect.poll(()=>page.evaluate(()=>window.order)).toEqual(['module','initializer']);
});

test('external module evaluation including await finishes before initializer', async ({page}) => {
 await boot(page);
 let requests=0;
 await page.route('https://audit.example/module.js', r=>{requests++;return r.fulfill({contentType:'application/javascript',body:'await new Promise(r=>setTimeout(r,50));window.order.push("module");export const ready=true;'});});
 await page.evaluate(()=>{
  window.order=[];
  document.body.innerHTML='<script type="text/plain" data-cmp-original-type="module" data-cmp-category="analytics" src="https://audit.example/module.js"></script><script type="text/plain" data-cmp-category="analytics">window.order.push("initializer")</script>';
  CMP.acceptAll();
 });
 await expect.poll(()=>page.evaluate(()=>window.order)).toEqual(['module','initializer']);
 expect(requests).toBe(1);
});
