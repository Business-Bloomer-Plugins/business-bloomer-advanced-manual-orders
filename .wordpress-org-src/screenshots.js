const { chromium } = require('playwright');
const fs = require('fs');
const [, an, av, ln, lv] = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n');
const OUT = process.argv[3], ORDER = process.argv[4];
const B = 'http://localhost:8901/wp-admin/';
const pad = (r, x = 24, y = 24) => ({ x: Math.max(0, r.x - x), y: Math.max(0, r.y - y), width: r.width + 2 * x, height: r.height + 2 * y });
const union = (a, b) => { const x = Math.min(a.x, b.x), y = Math.min(a.y, b.y); return { x, y, width: Math.max(a.x + a.width, b.x + b.width) - x, height: Math.max(a.y + a.height, b.y + b.height) - y }; };
(async () => {
  const br = await chromium.launch();
  const ctx = await br.newContext({ viewport: { width: 1440, height: 1100 }, deviceScaleFactor: 1.5 });
  await ctx.addCookies([{ name: an, value: av, domain: 'localhost', path: '/' }, { name: ln, value: lv, domain: 'localhost', path: '/' }]);
  const p = await ctx.newPage();
  await p.addStyleTag && null;
  const clean = () => p.addStyleTag({ content: '#wpadminbar .ab-top-menu > li:not(#wp-admin-bar-site-name):not(#wp-admin-bar-my-account){display:none} .notice, .update-nag, #screen-meta-links{display:none !important}' });

  // 1. New order popup, customer search
  await p.goto(B + 'admin.php?page=wc-orders'); await clean();
  await p.click('a.page-title-action');
  await p.waitForSelector('#amowc-new-order:not([hidden])');
  await p.click('#amowc-new-order .amowc-new-fields .select2-selection');
  await p.keyboard.type('555');
  await p.waitForSelector('.select2-results__option:has-text("Giulia")', { timeout: 10000 });
  await p.waitForTimeout(400);
  let box = await p.locator('#amowc-new-order .amowc-modal__box').boundingBox();
  let dd = await p.locator('.select2-dropdown').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-1.png', clip: pad(union(box, dd)) });
  await p.keyboard.press('Escape');

  // 2. Add a product
  await p.goto(B + 'admin.php?page=wc-orders&action=edit&id=' + ORDER); await clean();
  await p.locator('.amowc-quick-add').scrollIntoViewIfNeeded();
  await p.click('.amowc-quick-add .select2-selection');
  await p.keyboard.type('hoodie');
  await p.waitForSelector('.select2-results__option:has-text("Blue")', { timeout: 10000 });
  await p.waitForTimeout(400);
  box = await p.locator('#woocommerce-order-items').boundingBox();
  dd = await p.locator('.select2-dropdown').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-2.png', clip: pad(union(box, dd), 0, 0) });
  await p.keyboard.press('Escape');

  // 3. Calculate shipping
  await p.click('button.amowc-calc-shipping');
  await p.waitForSelector('.amowc-add-shipping:visible');
  await p.waitForTimeout(400);
  box = await p.locator('.amowc-modal:not([hidden]) .amowc-modal__box').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-3.png', clip: pad(box, 120, 60) });
  await p.click('.amowc-modal:not([hidden]) .amowc-close');

  // 4. Order panel: pay link actions, currency, customer
  await p.evaluate(() => window.scrollTo(0, 0));
  await p.waitForTimeout(300);
  box = await p.locator('#woocommerce-order-data').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-4.png', clip: pad(box, 0, 0) });

  // 5. Orders list with Duplicate
  await p.goto(B + 'admin.php?page=wc-orders'); await clean();
  box = await p.locator('.wp-list-table').boundingBox();
  const head = await p.locator('h1.wp-heading-inline').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-5.png', clip: pad({ x: box.x, y: head.y, width: box.width, height: Math.min(box.y + box.height, head.y + 480) - head.y }, 12, 12) });

  // 6. Settings
  await p.goto(B + 'admin.php?page=wc-settings&tab=advanced&section=amowc'); await clean();
  box = await p.locator('#mainform').boundingBox();
  await p.screenshot({ path: OUT + '/screenshot-6.png', clip: { x: box.x, y: box.y, width: box.width, height: box.height } });
  await br.close();
})().catch(e => { console.log('FAIL ' + e.message.split('\n')[0]); process.exit(1); });
