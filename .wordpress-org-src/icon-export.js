const { chromium } = require('playwright');
(async () => {
  const [html, out] = process.argv.slice(2);
  const br = await chromium.launch();
  for (const [size, scale] of [[128, 0.5], [256, 1]]) {
    const p = await br.newPage({ viewport: { width: 256, height: 256 }, deviceScaleFactor: scale });
    await p.goto('file://' + html);
    await p.locator('.icon').screenshot({ path: `${out}/icon-${size}x${size}.png` });
    await p.close();
  }
  await br.close();
})();
