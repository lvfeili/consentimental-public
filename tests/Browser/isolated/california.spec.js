const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const asset = name => fs.readFileSync(path.join(__dirname, '../../../assets/js/', name + '.js'), 'utf8');

async function boot(context, page, options = {}) {
 const requests = [], posts = [];
 const config = {cookieName:'cmp_consent', restUrl:'https://audit.example/consent', privacyUrl:'https://audit.example/privacy-choice', californiaEnabled:true,
  configVersion:1, expiryDays:180, reloadOnWithdrawal:false,
  categories:[{slug:'marketing',required:false}], services:{
   ads:{category:'marketing',name:'Ads',saleSharing:'involved'},
   safe:{category:'marketing',name:'Safe',saleSharing:'not_involved'},
   unknown:{category:'marketing',name:'Unknown',saleSharing:'unreviewed'} }};
 if (options.gpc) await context.addInitScript(() => Object.defineProperty(navigator, 'globalPrivacyControl', {value:true}));
 if (options.header) await context.setExtraHTTPHeaders({'Sec-GPC':'1'});
 if (options.previous) await context.addCookies([{name:'cmp_consent', value:encodeURIComponent(JSON.stringify({consent_id:'old',config_version:1,expiry:9999999999,categories:{marketing:true},services:{ads:true,safe:true,unknown:true}})),domain:'audit.example',path:'/'}]);
 let completeCheck;
 const wait = options.hold ? new Promise(resolve => completeCheck = resolve) : Promise.resolve();
 await context.route('https://audit.example/**', async route => {
  const url = route.request().url();
  if (url.endsWith('/privacy-choice')) {
   if (route.request().method() === 'POST') {
    posts.push(route.request().postDataJSON());
    return route.fulfill({status:options.failPost ? 503 : 200,contentType:'application/json',body:'{}'});
   }
   await wait;
   return route.fulfill({status:options.failCheck ? 503 : 200, contentType:'application/json',body:JSON.stringify({enabled:true,gpc:route.request().headers()['sec-gpc']==='1',opted_out:false})});
  }
  if (url.endsWith('/consent')) return route.fulfill({contentType:'application/json',body:'{}'});
  const markup = ['ads','safe','unknown'].map(id => '<iframe data-cmp-service="'+id+'" data-cmp-category="marketing" data-cmp-src="https://vendor.example/'+id+'"></iframe>').join('') +
   '<script type="text/plain" data-cmp-category="marketing">window.unassignedRan=true</script>' +
   '<section data-cmp-california data-confirmed="Opt-out active" data-gpc="GPC honored" data-waiting="Checking" data-error="Sync failed"><button data-cmp-sale-opt-out>Do Not Sell or Share My Personal Information</button><p data-cmp-sale-status role="status"></p><button data-cmp-sale-retry hidden>Retry</button></section>';
  return route.fulfill({contentType:'text/html',body:'<!doctype html><html><head><script>var cmpConfig='+JSON.stringify(config)+';'+asset('consent')+'</script></head><body>'+markup+'<script>var cmpBlockingL10n={enableService:"Allow %1$s",privacyBlocked:"Unavailable",openPrivacySettings:"Settings"};'+asset('blocking')+'</script><script>'+asset('california')+'</script></body></html>'});
 });
 await context.route('https://vendor.example/**', route => {requests.push(route.request().url());return route.fulfill({contentType:'text/html',body:'Provider'});});
 await page.goto('https://audit.example/');
 return {requests, posts, config, completeCheck};
}

test('GPC wins over previous and new consent without blocking a reviewed unrelated service', async ({context,page}) => {
 const {requests,posts} = await boot(context,page,{gpc:true,previous:true});
 await expect(page.getByRole('status')).toHaveText('GPC honored');
 await expect.poll(() => requests.length).toBe(1);
 expect(requests[0]).toContain('/safe');
 await page.evaluate(() => {CMP.acceptAll();CMP.enableService('ads');CMP.setConsent({marketing:true},{ads:true,unknown:true,safe:true});});
 expect(await page.evaluate(() => [CMP.hasServiceConsent('ads'),CMP.hasServiceConsent('unknown'),CMP.hasServiceConsent('safe'),!!window.unassignedRan])).toEqual([false,false,true,false]);
 expect(posts[0]).toEqual({opted_out:true,source:'gpc'});
});

test('cached page waits for header-only GPC before releasing affected services', async ({context,page}) => {
 const {requests,completeCheck} = await boot(context,page,{header:true,previous:true,hold:true});
 await expect(page.locator('iframe[data-cmp-service=ads]')).not.toHaveAttribute('src');
 completeCheck();
 await expect(page.getByRole('status')).toHaveText('GPC honored');
 await expect.poll(() => requests.length).toBe(1);
 expect(requests[0]).toContain('/safe');
});

test('manual opt-out unloads only affected content and survives reset, expiry and configuration changes', async ({context,page}) => {
 const {config} = await boot(context,page);
 await expect.poll(() => page.evaluate(() => CMP.getPrivacyChoice().ready)).toBe(true);
 await page.evaluate(() => CMP.acceptAll());
 await expect(page.locator('iframe[src]')).toHaveCount(2);
 await page.getByRole('button',{name:'Do Not Sell or Share My Personal Information'}).click();
 await expect(page.getByRole('status')).toHaveText('Opt-out active');
 await expect(page.locator('iframe[src]')).toHaveCount(1);
 await page.evaluate(() => {CMP.reset();CMP.acceptAll();CMP.enableService('ads');});
 expect(await page.evaluate(() => CMP.hasServiceConsent('ads'))).toBe(false);
 config.configVersion = 2;
 await page.evaluate(() => document.cookie='cmp_consent=;max-age=0;path=/');
 await page.reload();
 await expect.poll(() => page.evaluate(() => CMP.getPrivacyChoice().ready)).toBe(true);
 await page.evaluate(() => CMP.acceptAll());
 expect(await page.evaluate(() => CMP.hasServiceConsent('ads'))).toBe(false);
 await expect(page.locator('iframe[src]')).toHaveCount(1);
});

test('opt-out propagates to an already open tab', async ({context,page}) => {
 await boot(context,page);
 await expect.poll(() => page.evaluate(() => CMP.getPrivacyChoice().ready)).toBe(true);
 await page.evaluate(() => CMP.acceptAll());
 const other = await context.newPage();
 await other.goto('https://audit.example/');
 await expect(other.locator('iframe[src]')).toHaveCount(2);
 await page.evaluate(() => CMP.optOutSaleSharing());
 await expect(other.locator('iframe[src]')).toHaveCount(1);
 expect(await other.evaluate(() => CMP.hasServiceConsent('ads'))).toBe(false);
});

test('failed header check stays closed and opt-out POST failure is visible without undoing choice', async ({context,page}) => {
 await boot(context,page,{previous:true,failCheck:true,failPost:true});
 await expect(page.getByRole('status')).toContainText('Sync failed');
 await page.getByRole('button',{name:'Do Not Sell or Share My Personal Information'}).click();
 await expect(page.getByRole('status')).toContainText('Opt-out active');
 expect(await page.evaluate(() => CMP.hasServiceConsent('ads'))).toBe(false);
 await expect(page.getByRole('button',{name:'Retry',exact:true})).toBeVisible();
});
