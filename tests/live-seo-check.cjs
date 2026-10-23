// Read-only audit of public HTTP responses. Run: node tests/live-seo-check.cjs
const assert = require('node:assert/strict');
const base = 'https://osadafabryczna.pl';
const failures = [], warnings = [], pages = new Map();
let checks = 0;
function check(condition, label) {
  checks++;
  if (!condition) failures.push(label);
}
async function get(url) {
  const response = await fetch(url, { signal: AbortSignal.timeout(25000) });
  return { status: response.status, headers: response.headers, body: await response.text(), url: response.url };
}
const locations = xml => [...xml.matchAll(/<loc>(.*?)<\/loc>/g)].map(match => match[1].replaceAll('&amp;', '&'));
function attr(tag, key) {
  return tag.match(new RegExp(`\\b${key}=["']([^"']*)["']`, 'i'))?.[1] || '';
}
async function audit() {
  const robots = await get(`${base}/robots.txt`);
  check(robots.status === 200, 'robots.txt: HTTP 200');
  check(!/^Disallow:\s*\/\s*$/im.test(robots.body), 'robots.txt: no blanket crawler block');
  check(robots.body.includes(`${base}/wp-sitemap.xml`), 'robots.txt: sitemap listed');
  const sitemap = await get(`${base}/wp-sitemap.xml`);
  check(sitemap.status === 200, 'sitemap index: HTTP 200');
  check(!sitemap.body.includes('wp-sitemap-users-'), 'sitemap index: author directory omitted');
  check(!sitemap.body.includes('<?xml-stylesheet'), 'sitemap index: no browser XSLT dependency');
  const authorMap = await get(`${base}/wp-sitemap-users-1.xml`);
  check(authorMap.status === 404, `author sitemap: HTTP 404 (actual ${authorMap.status})`);
  const users = await get(`${base}/wp-json/wp/v2/users?_fields=id,slug,name,link`);
  check([401, 403, 404].includes(users.status), `anonymous users API: denied (actual ${users.status})`);
  const authorProbe = await fetch(`${base}/?author=1`, { redirect: 'manual', signal: AbortSignal.timeout(25000) });
  check(authorProbe.status === 404 && !authorProbe.headers.get('location'), `author ID lookup: 404 without named redirect (actual ${authorProbe.status})`);
  const urls = new Set([`${base}/`, `${base}/eng/`, `${base}/budynki/`, `${base}/eng/budynki/`]);
  for (const url of locations(sitemap.body).filter(url => url.includes('wp-sitemap-posts-'))) {
    assert.ok(url.startsWith(`${base}/`), 'sitemap URL stays on audited site');
    const child = await get(url);
    check(child.status === 200, `${url}: HTTP 200`);
    check(!child.body.includes('<?xml-stylesheet'), `${url}: no browser XSLT dependency`);
    for (const page of locations(child.body)) {
      assert.ok(page.startsWith(`${base}/`), 'page URL stays on audited site');
      urls.add(page);
    }
  }
  const queue = [...urls];
  await Promise.all(Array.from({ length: 4 }, async () => {
    while (queue.length) {
      const url = queue.shift();
      try {
        const result = await get(url);
        pages.set(url, result);
        check(result.status === 200, `${url}: HTTP 200`);
        check(result.url === url, `${url}: sitemap URL does not redirect`);
        const meta = result.body.match(/<meta\b[^>]*>/gi) || [];
        const links = result.body.match(/<link\b[^>]*>/gi) || [];
        const descriptions = meta.filter(tag => attr(tag, 'name') === 'description');
        check(descriptions.length === 1 && !!attr(descriptions[0], 'content'), `${url}: one nonempty meta description`);
        const canonicals = links.filter(tag => attr(tag, 'rel') === 'canonical');
        check(canonicals.length === 1 && attr(canonicals[0], 'href') === url, `${url}: one self-referencing canonical`);
        check(!meta.some(tag => attr(tag, 'name') === 'robots' && /noindex|nofollow/.test(attr(tag, 'content'))), `${url}: no noindex/nofollow`);
        check(!/noindex/.test(result.headers.get('x-robots-tag') || ''), `${url}: no HTTP noindex`);
        const english = url.includes('/eng/');
        check(result.body.includes(`lang="${english ? 'en-GB' : 'pl-PL'}"`), `${url}: HTML language`);
        const scripts = [...result.body.matchAll(/<script\b[^>]*type=["']application\/ld\+json["'][^>]*>(.*?)<\/script>/gs)];
        check(scripts.length === 1, `${url}: one schema graph`);
        const graph = JSON.parse(scripts[0]?.[1] || '{}')['@graph'] || [];
        const page = graph.find(node => ['WebPage', 'CollectionPage'].includes(node['@type']));
        check(page?.url === url && page?.inLanguage === (english ? 'en' : 'pl'), `${url}: schema page URL/language`);
        if (/\/(?:budynki|budynek)\/[^/]+\/$/.test(url)) {
          const building = graph.find(node => node['@type'] === 'LandmarksOrHistoricalBuildings');
          check(!!building?.name && !!building?.description, `${url}: building schema`);
          check(!!building?.geo && Math.abs(building.geo.latitude) <= 90 && Math.abs(building.geo.longitude) <= 180, `${url}: building coordinates`);
        }
        result.alternates = new Map(links.filter(tag => attr(tag, 'hreflang')).map(tag => [attr(tag, 'hreflang'), attr(tag, 'href')]));
        if (english && /<title>Budynki\b/.test(result.body)) warnings.push(`${url}: Polish archive title in English version`);
      } catch (error) { failures.push(`${url}: ${error.message}`); }
    }
  }));
  for (const [url, page] of pages) {
    for (const [language, alternate] of page.alternates || []) {
      if (alternate === url) continue;
      const target = pages.get(alternate);
      check(!!target && [...(target.alternates?.values() || [])].includes(url), `${url}: reciprocal ${language} alternate`);
    }
  }
  const guide = await get(`${base}/llms.txt`);
  check(guide.status === 200, `llms.txt: HTTP 200 (actual ${guide.status})`);
  check((guide.headers.get('content-type') || '').includes('text/plain'), 'llms.txt: plain text response');
  check(guide.body.includes('Citation guidance'), 'llms.txt: citation guidance');
  for (const language of ['pl', 'en']) {
    const result = await get(`${base}/wp-json/wp/v2/budynek?language=${language}&per_page=1&_fields=id,link`);
    check(result.status === 200 && Array.isArray(JSON.parse(result.body)), `${language}: public building API`);
    check(Number(result.headers.get('x-wp-total')) > 0, `${language}: nonempty building API`);
  }
  console.log(JSON.stringify({ pages: pages.size, checks, failures, warnings }, null, 2));
  process.exitCode = failures.length ? 1 : 0;
}
audit().catch(error => { console.error(error); process.exitCode = 1; });
