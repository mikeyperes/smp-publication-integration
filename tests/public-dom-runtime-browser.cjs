'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

(async function () {
    const browser = await chromium.launch({
        headless: true,
        executablePath: process.env.CHROMIUM_PATH || undefined,
        args: ['--no-sandbox', '--disable-dev-shm-usage']
    });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
    });

    await page.setContent(`<!doctype html>
<html><body class="smpi-runtime-breadcrumbs smpi-runtime-article-markup smpi-runtime-article-headings smpi-runtime-article-dropcap smpi-runtime-article-numbered-lists smpi-runtime-numbered-list-nlist03">
<header style="display:block;width:1200px;height:80px">Header</header>
<main data-smp-ajax-root="content"><article><div class="entry-content"><h2>Initial heading</h2><p id="initial-lead">Initial lead.</p><ol><li><strong>Initial item</strong><p>Copy.</p></li></ol><h2>Contact Information</h2><p id="initial-contact" class="smpi-article-lead">Press desk: <a href="tel:13234506187">1-323-450-6187</a></p></div></article></main>
<template data-smp-ajax-companion="smpi-breadcrumbs"><div class="smpi-breadcrumbs-band" data-smpi-header-selectors='["header"]'><nav aria-label="breadcrumbs"><p><a href="/">Home</a><span aria-current="page">Initial</span></p></nav></div></template>
</body></html>`);
    await page.addScriptTag({ path: path.resolve(__dirname, '../assets/frontend/public-dom.js') });
    await page.waitForFunction(() => document.querySelector('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]'));

    let state = await page.evaluate(() => ({
        breadcrumbCount: document.querySelectorAll('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]').length,
        breadcrumb: document.querySelector('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"] [aria-current="page"]')?.textContent,
        afterHeader: document.querySelector('header')?.nextElementSibling?.getAttribute('data-smp-ajax-companion-rendered'),
        article: document.querySelector('.entry-content')?.classList.contains('smpi-article-content'),
        heading: document.querySelector('.entry-content h2')?.classList.contains('smpi-article-heading'),
        dropcap: document.querySelector('#initial-lead')?.classList.contains('smpi-article-lead'),
        staleDropcap: document.querySelector('#initial-contact')?.classList.contains('smpi-article-lead'),
        list: document.querySelector('.entry-content ol')?.classList.contains('smpi-numbered-list--nlist03')
    }));
    assert.deepEqual(state, {
        breadcrumbCount: 1,
        breadcrumb: 'Initial',
        afterHeader: 'smpi-breadcrumbs',
        article: true,
        heading: true,
        dropcap: true,
        staleDropcap: false,
        list: true
    });

    await page.evaluate(() => {
        document.querySelectorAll('template[data-smp-ajax-companion="smpi-breadcrumbs"],[data-smp-ajax-companion-rendered="smpi-breadcrumbs"],[data-smpi-breadcrumbs-injected]').forEach((node) => node.remove());
        const template = document.createElement('template');
        template.setAttribute('data-smp-ajax-companion', 'smpi-breadcrumbs');
        template.innerHTML = '<div class="smpi-breadcrumbs-band" data-smpi-header-selectors=\'["header"]\'><nav aria-label="breadcrumbs"><p><a href="/">Home</a><span aria-current="page">Target</span></p></nav></div>';
        document.body.appendChild(template);
        const root = document.createElement('main');
        root.setAttribute('data-smp-ajax-root', 'content');
        root.innerHTML = '<article><div class="entry-content"><h2>Target heading</h2><p id="target-lead">Target lead.</p><ol><li><strong>Target item</strong><p>Copy.</p></li></ol><h2>Media Contact</h2><p id="target-contact" class="smpi-article-lead">Newsroom: <a href="mailto:news@example.com">news@example.com</a></p></div></article>';
        document.querySelector('main[data-smp-ajax-root]').replaceWith(root);
        document.dispatchEvent(new CustomEvent('smp:content-ready', { detail: { root, url: '/target/' } }));
        document.dispatchEvent(new CustomEvent('smp:content-ready', { detail: { root, url: '/target/' } }));
    });

    state = await page.evaluate(() => ({
        templateCount: document.querySelectorAll('template[data-smp-ajax-companion="smpi-breadcrumbs"]').length,
        breadcrumbCount: document.querySelectorAll('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]').length,
        breadcrumb: document.querySelector('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"] [aria-current="page"]')?.textContent,
        article: document.querySelector('.entry-content')?.classList.contains('smpi-article-content'),
        heading: document.querySelector('.entry-content h2')?.classList.contains('smpi-article-heading'),
        dropcap: document.querySelector('#target-lead')?.classList.contains('smpi-article-lead'),
        staleDropcap: document.querySelector('#target-contact')?.classList.contains('smpi-article-lead'),
        list: document.querySelector('.entry-content ol')?.classList.contains('smpi-numbered-list--nlist03')
    }));
    assert.deepEqual(state, {
        templateCount: 1,
        breadcrumbCount: 1,
        breadcrumb: 'Target',
        article: true,
        heading: true,
        dropcap: true,
        staleDropcap: false,
        list: true
    });

    await page.evaluate(() => {
        document.body.classList.remove('smpi-runtime-breadcrumbs');
        document.querySelectorAll('template[data-smp-ajax-companion="smpi-breadcrumbs"],[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]').forEach((node) => node.remove());
        const root = document.querySelector('main[data-smp-ajax-root]');
        document.dispatchEvent(new CustomEvent('smp:content-ready', { detail: { root, url: '/without-breadcrumbs/' } }));
    });
    assert.equal(await page.locator('[data-smp-ajax-companion-rendered="smpi-breadcrumbs"]').count(), 0);
    assert.deepEqual(errors, []);

    await browser.close();
    process.stdout.write('PASS: public DOM runtime replaces breadcrumb companions and reinitializes article markup idempotently.\n');
})().catch((error) => {
    process.stderr.write(`FAIL: ${error.stack || error.message}\n`);
    process.exit(1);
});
