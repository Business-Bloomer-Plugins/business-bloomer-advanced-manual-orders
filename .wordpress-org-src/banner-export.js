const { chromium } = require('playwright');
(async () => {
  const [html, out] = process.argv.slice(2);
  const br = await chromium.launch();
  for (const [w, scale] of [[772, 1], [1544, 2]]) {
    const p = await br.newPage({ viewport: { width: 772, height: 250 }, deviceScaleFactor: scale });
    await p.goto('file://' + html);
    await p.evaluate(() => document.fonts.ready);
    await p.screenshot({ path: `${out}/banner-${w}x${w / 772 * 250}.png`, clip: { x: 0, y: 0, width: 772, height: 250 } });
    await p.close();
  }
  await br.close();
})();
