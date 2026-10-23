// HTTP integration with real WordPress routing, without changing rewrite options.
const assert = require('node:assert/strict');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
const php = process.env.PHP_BIN || path.join(process.env.APPDATA, 'Local/lightning-services/php-8.2.29+0/bin/win64/php.exe');
const extensions = process.env.PHP_EXTENSION_DIR || path.join(path.dirname(php), 'ext');
let child;
(async () => {
  const probe = net.createServer();
  await new Promise(resolve => probe.listen(0, '127.0.0.1', resolve));
  const port = probe.address().port;
  await new Promise(resolve => probe.close(resolve));
  let startup = '';
  child = spawn(php, ['-n', '-d', `extension_dir=${extensions}`, '-d', 'extension=mysqli', '-d', 'extension=mbstring',
    '-d', `mysqli.default_port=${process.env.OSADA_MYSQL_PORT || '10029'}`,
    '-S', `127.0.0.1:${port}`, path.join(__dirname, 'llms-router.php')], { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => reject(new Error(`PHP startup timeout: ${startup}`)), 10000);
    child.on('error', error => { clearTimeout(timeout); reject(error); });
    child.stderr.on('data', data => {
      startup += data;
      if (startup.includes('Development Server')) { clearTimeout(timeout); resolve(); }
    });
  });
  const base = `http://127.0.0.1:${port}`;
  let checks = 0;
  function check(condition, label) { assert.ok(condition, label); checks++; }
  const response = await fetch(`${base}/llms.txt`);
  const body = await response.text();
  check(response.status === 200, 'missing physical file resolves to HTTP 200');
  check(response.headers.get('content-type') === 'text/plain; charset=UTF-8', 'UTF-8 plain text');
  check(response.headers.get('x-content-type-options') === 'nosniff', 'no content sniffing');
  check(response.headers.get('cache-control') === 'no-cache', 'guide revalidated after updates');
  check(body.startsWith('# Osada Fabryczna Żyrardowa'), 'correct source guide');
  check(body.includes('Citation guidance'), 'citation instructions present');
  check(body.includes('http://osadafabryczna-produkcja.local/wp-sitemap.xml'), 'URLs follow WordPress home URL');
  check(!body.includes('<html'), 'no HTML fallback or PHP warnings');
  const head = await fetch(`${base}/llms.txt`, { method: 'HEAD' });
  check(head.status === 200 && '' === await head.text(), 'HEAD returns headers without body');
  const query = await fetch(`${base}/llms.txt?check=1`);
  check(query.status === 200 && (await query.text()) === body, 'query string does not break route');
  const unrelated = await fetch(`${base}/unrelated/llms.txt`);
  check(unrelated.status === 404, 'unrelated URL not intercepted');
  const sitemap = await fetch(`${base}/wp-sitemap.xml`);
  const sitemapBody = await sitemap.text();
  check(sitemap.status === 200 && sitemap.headers.get('content-type').includes('application/xml'), 'sitemap stays XML with HTTP 200');
  check(sitemapBody.includes('wp-sitemap-posts-budynek-1.xml') && !sitemapBody.includes('wp-sitemap-users'), 'building sitemap retained, author sitemap omitted');
  check(!sitemapBody.includes('<?xml-stylesheet'), 'sitemap index has no XSLT instruction');
  const buildings = await fetch(`${base}/wp-sitemap-posts-budynek-1.xml`);
  const buildingsBody = await buildings.text();
  check(buildings.status === 200 && buildingsBody.includes('<urlset'), 'building sitemap works');
  check(!buildingsBody.includes('<?xml-stylesheet'), 'building sitemap has no XSLT instruction');
  const authorSitemap = await fetch(`${base}/wp-sitemap-users-1.xml`);
  check(authorSitemap.status === 404, 'direct author sitemap returns 404');
  const querySitemap = await fetch(`${base}/?sitemap=users&paged=1`);
  check(querySitemap.status === 404, 'query-string author sitemap also returns 404');
  const authorId = await fetch(`${base}/?author=1`, { redirect: 'manual' });
  check(authorId.status === 404 && !authorId.headers.get('location'), 'author ID does not redirect to public slug');
  const authorSlug = await fetch(`${base}/author/unused-privacy-test/`, { redirect: 'manual' });
  check(authorSlug.status === 404 && !authorSlug.headers.get('location'), 'author archive returns 404');
  for (const route of ['/wp-json/wp/v2/users', '/wp-json/wp/v2/users/1', '/?rest_route=/wp/v2/users']) {
    const users = await fetch(`${base}${route}`);
    const data = await users.json();
    check(users.status === 401 && data.code === 'osada_user_directory_private', 'anonymous user directory unavailable');
  }
  const headUsers = await fetch(`${base}/wp-json/wp/v2/users`, { method: 'HEAD' });
  check(headUsers.status === 401 && '' === await headUsers.text(), 'anonymous HEAD cannot expose users');
  for (const language of ['pl', 'en']) {
    const api = await fetch(`${base}/wp-json/wp/v2/budynek?language=${language}&per_page=1&_fields=id,link`);
    check(api.status === 200 && Array.isArray(await api.json()), `${language} public map API still works`);
  }
  console.log(`${checks} llms.txt, sitemap and account privacy HTTP integration checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; }).finally(() => { child?.kill(); });
