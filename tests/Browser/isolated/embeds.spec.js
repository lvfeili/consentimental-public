const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const asset = name => fs.readFileSync(path.join(__dirname, '../../../assets/js/', name + '.js'), 'utf8');

async function boot(page, srcdoc = false) {
 const markup = '<link rel="preconnect" href="https://www.youtube-nocookie.com">' +
  '<link rel="prefetch" href="https://www.youtube-nocookie.com/embed/one">' +
  (srcdoc ? '<iframe srcdoc="&lt;img src=&quot;https://www.youtube-nocookie.com/preview.png&quot;&gt;&lt;img src=&quot;https://player.vimeo.com/preview.png&quot;&gt;"></iframe>' :
   '<iframe title="Video" src="https://www.youtube-nocookie.com/embed/one"></iframe><iframe title="Other video" src="https://player.vimeo.com/video/two"></iframe>');
 const rewritten = execFileSync('php', ['-r', 'require $argv[1]; $r=new Consentimental\\Services\\ServiceRegistry(new Consentimental\\Services\\CustomServiceRepository()); (new Consentimental\\Services\\BuiltInServiceLibrary())->register($r); echo (new Consentimental\\Blocking\\DomRewriter($r))->rewrite($argv[2]);', path.join(__dirname, '../../bootstrap.php'), markup], { encoding: 'utf8' });
 const config = {cookieName:'cmp_consent', restUrl:'https://audit.example/consent', configVersion:2, expiryDays:180, explicitServiceConsent:true,
  categories:[{slug:'marketing',required:false}], services:{
   youtube:{category:'marketing',name:'YouTube',provider:'Google'}, vimeo:{category:'marketing',name:'Vimeo',provider:'Vimeo'} }};
 const requests = [];
 await page.route('**/*', route => {
  const url = route.request().url();
  if (url.startsWith('https://audit.example/')) return route.fulfill({contentType:'text/html', body:'<!doctype html><html><head><script>var cmpConfig='+JSON.stringify(config)+';'+asset('consent')+'</script></head><body>'+rewritten+'<script>window.cmpBlockingL10n={enableService:"Allow %1$s and load content",openPrivacySettings:"Privacy settings"};'+asset('blocking')+'</script></body></html>'});
  requests.push(url);
  return route.fulfill({contentType:'text/html',body:'<p>Provider content</p>'});
 });
 await page.goto('https://audit.example/');
 return requests;
}

test('provider button loads only that provider, persists, and withdrawal unloads it', async ({page}) => {
 const requests = await boot(page);
 await expect(page.locator('.cmp-embed-placeholder')).toHaveCount(2);
 expect(requests).toEqual([]);
 await expect(page.locator('link[rel=preconnect],link[rel=prefetch]')).toHaveCount(0);
 await page.evaluate(() => CMP.setConsent({marketing:true}, {}));
 expect(await page.locator('iframe[src]').count()).toBe(0);
 await page.getByRole('button', {name:'Allow YouTube and load content'}).click();
 await expect.poll(() => requests.length).toBe(1);
 expect(requests[0]).toContain('youtube-nocookie.com/embed/one');
 expect(await page.evaluate(() => CMP.hasServiceConsent('vimeo'))).toBe(false);
 await page.reload();
 await expect.poll(() => requests.length).toBe(2);
 await page.evaluate(() => CMP.rejectAll());
 await expect(page.locator('iframe[src]')).toHaveCount(0);
 await expect(page.locator('.cmp-embed-placeholder')).toHaveCount(2);
 await page.reload();
 expect(requests).toHaveLength(2);
});

test('srcdoc waits for every known provider and is removed on withdrawal', async ({page}) => {
 const requests = await boot(page, true);
 expect(requests).toEqual([]);
 await expect(page.locator('iframe[srcdoc]')).toHaveCount(0);
 await page.evaluate(() => CMP.enableService('youtube'));
 await expect(page.locator('iframe[srcdoc]')).toHaveCount(0);
 expect(requests).toEqual([]);
 await page.evaluate(() => CMP.enableService('vimeo'));
 await expect.poll(() => requests.length).toBe(2);
 await page.evaluate(() => CMP.setConsent({marketing:false}, {youtube:true,vimeo:false}));
 await expect(page.locator('iframe[srcdoc]')).toHaveCount(0);
 await expect(page.locator('.cmp-embed-placeholder')).toHaveCount(1);
});
