const fs = require('fs');
const puppeteer = require('puppeteer-core');

const target = 'https://validator.schema.org/#url=https%3A%2F%2Fherforward.com%2Fpersonal-journey-that-inspired-marcia-neumanns-coaching-philosophy%2F';
const expectedTypes = [
  'WebPage',
  'NewsArticle',
  'NewsMediaOrganization',
  'ImageObject',
  'BreadcrumbList',
  'WebSite',
  'Person',
];
const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    executablePath: process.env.CHROME_BIN || '/usr/bin/google-chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1600,1200'],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1600, height: 1200, deviceScaleFactor: 1 });

  const consoleErrors = [];
  const pageErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => pageErrors.push(error.message));

  await page.goto(target, { waitUntil: 'domcontentloaded', timeout: 90000 });

  let bodyText = '';
  let loaded = false;
  for (let attempt = 0; attempt < 90; attempt += 1) {
    bodyText = await page.evaluate(() => document.body?.innerText || '');
    loaded = expectedTypes.every((type) => bodyText.includes(type));
    if (loaded) break;
    await delay(2000);
  }

  const ui = await page.evaluate((types) => {
    const isVisible = (element) => {
      const style = window.getComputedStyle(element);
      const box = element.getBoundingClientRect();
      return style.display !== 'none' && style.visibility !== 'hidden' && box.width > 0 && box.height > 0;
    };
    const elements = Array.from(document.querySelectorAll('body *'));
    const lines = (document.body?.innerText || '')
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean);
    return {
      title: document.title,
      url: location.href,
      typeVisibility: Object.fromEntries(types.map((type) => [
        type,
        elements.filter((element) => isVisible(element) && (element.textContent || '').trim() === type).length,
      ])),
      statusLines: lines.filter((line) => /items? detected|errors?|warnings?/i.test(line)).slice(0, 40),
      relevantLines: lines.filter((line) => types.includes(line)).slice(0, 40),
      bodyText: lines.slice(0, 500).join('\n'),
    };
  }, expectedTypes);

  const screenshot = 'artifacts/schema-validator-exact-ui.png';
  await page.screenshot({ path: screenshot, fullPage: true });
  const proof = {
    target,
    loaded,
    expectedTypes,
    ui,
    consoleErrors,
    pageErrors,
    screenshot,
  };
  fs.writeFileSync('artifacts/schema-validator-exact-ui.json', `${JSON.stringify(proof, null, 2)}\n`);
  console.log(JSON.stringify({ ...proof, ui: { ...ui, bodyText: ui.bodyText.slice(0, 4000) } }, null, 2));

  await browser.close();
  if (!loaded) process.exitCode = 1;
})().catch((error) => {
  console.error(error.stack || error.message);
  process.exit(1);
});
