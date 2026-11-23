import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { scenes } from './scenes.mjs';
import { renderGuides } from './render.mjs';

const required = (name) => {
    if (!process.env[name]) throw new Error(`Define ${name}.`);
    return process.env[name];
};
const puppeteer = createRequire(import.meta.url)(required('VELVET_PUPPETEER'));
const workspace = mkdtempSync(join(tmpdir(), 'velvet-videos-'));
const port = process.env.VELVET_RECORDING_PORT ?? '18743';
const origin = `http://127.0.0.1:${port}`;
const env = {
    // Explicit credentials and stores for the isolated Laravel instance.
    PATH: process.env.PATH, APP_ENV: 'testing', APP_DEBUG: 'false',
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`, APP_URL: origin, ASSET_URL: origin,
    APP_CONFIG_CACHE: join(workspace, 'config.php'), VELVET_RECORDING_DIR: workspace,
    DB_CONNECTION: 'sqlite', DB_DATABASE: join(workspace, 'demo.sqlite'),
    CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_SECURE_COOKIE: 'false',
    MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync', BROADCAST_CONNECTION: 'null',
    GROQ_API_KEY: 'fake-demo-key', LOG_CHANNEL: 'stderr',
};
const run = (command, args, options = {}) => {
    const result = spawnSync(command, args, { encoding: 'utf8', ...options });
    if (result.status !== 0) throw new Error(`${command}: ${result.stderr || result.error || result.stdout}`);
    return result.stdout;
};

writeFileSync(env.DB_DATABASE, '');
mkdirSync(join(workspace, 'sessions'));
run('php', ['artisan', 'migrate', '--force', '--no-interaction'], { env });
const fixtures = run('php', ['scripts/documentation/fixtures.php'], { env });
writeFileSync(join(workspace, 'fixtures.json'), fixtures);
let fixtureData;
try {
    fixtureData = JSON.parse(fixtures);
} catch {
    throw new Error(fixtures);
}
const { modules, urls } = fixtureData;
const selected = process.argv.slice(2);
const keys = selected.length ? selected : Object.keys(modules);
console.log(`Capturas temporales: ${workspace}`);

const server = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', 'public', 'scripts/documentation/local-router.php'], { env, stdio: 'ignore' });
const delay = (ms) => new Promise((done) => setTimeout(done, ms));
const viewport = { width: 1280, height: 720 };
const escape = (value) => value.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
let browser;

async function waitForServer() {
    for (let i = 0; i < 40; i++) {
        if (server.exitCode !== null) throw new Error('El servidor temporal no inició.');
        try { await fetch(`${origin}/up`); return; } catch { await delay(100); }
    }
    throw new Error('El servidor temporal no responde.');
}

async function go(page, path) {
    const url = path.startsWith('@') ? urls[path.slice(1)] : path;
    const response = await page.goto(new URL(url, origin).href, { waitUntil: 'domcontentloaded' });
    if (response.status() >= 400) throw new Error(`${path} respondió ${response.status()}`);
    await page.waitForSelector('main', { timeout: 5000 });
}

async function checkSupport(page) {
    await browser.setCookie({ name: 'velvet_demo_role', value: 'admin', url: origin });
    for (const width of [390, 1280]) {
        await page.setViewport({ width, height: 720 });
        await go(page, '/admin/candidates');
        await page.waitForSelector('.velvet-launcher-toggle');
        const widthBefore = await page.$eval('main', (element) => element.getBoundingClientRect().width);
        const toggle = await page.$('.velvet-launcher-toggle');
        if (await toggle.evaluate((element) => !!element.closest('header'))) throw new Error('Soporte terminó dentro del navbar.');
        if (await page.$('.velvet-launcher-action')) throw new Error('Hay opciones montadas con el lanzador cerrado.');
        await toggle.click();
        await page.waitForSelector('.velvet-launcher-action');
        const pointer = await page.$eval('.velvet-launcher-actions', (element) => {
            const rect = element.getBoundingClientRect();
            const toggle = document.querySelector('.velvet-launcher-toggle').getBoundingClientRect();
            return document.elementFromPoint(rect.right - 5, (rect.bottom + toggle.top) / 2)?.closest('.velvet-launcher') === null;
        });
        const widthAfter = await page.$eval('main', (element) => element.getBoundingClientRect().width);
        if (!pointer || widthBefore !== widthAfter) throw new Error(`El soporte interfiere con el layout a ${width}px.`);
        await page.keyboard.press('Escape');
        await page.waitForSelector('.velvet-launcher-action', { hidden: true });
    }
    await page.setViewport(viewport);
    console.log('Lanzador flotante y área de clic verificados a 390 y 1280 px.');
}

async function checkVideo(page) {
    await browser.setCookie({ name: 'velvet_demo_role', value: 'admin', url: origin });
    await go(page, '/admin/documentation');
    await page.type('.functional-guides input[type="search"]', 'leads');
    await page.waitForFunction(() => document.getElementById('functional-guide-title')?.textContent === 'Leads');
    await page.click('button.functional-guides__action');
    await page.waitForFunction(() => document.querySelector('.functional-guides__video')?.readyState >= 1, { timeout: 6000 });
    await page.$eval('.functional-guides__video', async (video) => { video.muted = true; await video.play(); });
    await page.waitForFunction(() => document.querySelector('.functional-guides__video').currentTime > 0, { timeout: 6000 });
    console.log('El MP4 de Leads se reproduce dentro de Documentación.');
}

async function targetFor(page, target) {
    return page.evaluateHandle((selector, regex) => {
        const visible = (element) => element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden';
        if (selector) return [...document.querySelectorAll(selector)].find(visible) ?? null;
        const match = new RegExp(regex, 'i');
        const matches = (element) => visible(element) && match.test((element.textContent || '').trim());
        return [...document.querySelectorAll('.velvet-ai-panel button, [role="dialog"] button, main button, main a, main label, main summary')].find(matches)
            ?? [...document.querySelectorAll('main h2, main h3, main select option')].find(matches)
            ?? null;
    }, typeof target === 'string' ? target : null, target instanceof RegExp ? target.source : null);
}

async function takeScreenshot(page, path) {
    const { theme, cookieBanner } = await page.evaluate(() => ({
        theme: document.documentElement.dataset.theme,
        cookieBanner: Boolean(document.querySelector('.cookie-consent-banner')),
    }));
    assert.equal(theme, 'dark', 'Cada escena de la documentación debe capturarse en modo oscuro.');
    assert.equal(cookieBanner, false, 'El aviso de cookies está cubriendo el flujo de prueba.');
    await page.screenshot({ path, quality: 82 });
}

async function captureStep(page, folder, index, [target, path, value, mode], fallbackUrl) {
    if (path) await go(page, path);
    let handle = (await targetFor(page, target)).asElement();
    if (!handle && !path && !page.url().endsWith(fallbackUrl)) {
        await go(page, fallbackUrl);
        handle = (await targetFor(page, target)).asElement();
    }
    const missing = !handle;
    handle ??= await page.$('main h1, main');
    await handle.evaluate((element) => element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'instant' }));
    await delay(80);
    const geometry = await handle.evaluate((element) => {
        const rect = element.getBoundingClientRect();
        return {
            x: Math.max(16, Math.min(innerWidth - 18, rect.left + Math.min(rect.width / 2, 150))),
            y: Math.max(10, Math.min(innerHeight - 20, rect.top + Math.min(rect.height / 2, 36))),
            interactive: element.matches('button, a, input, select, textarea, label, summary, [role="button"], [role="switch"]'),
            type: element.getAttribute('type'),
            external: element instanceof HTMLAnchorElement && element.origin !== location.origin,
            submit: element instanceof HTMLButtonElement && element.type === 'submit',
            newTab: element.target === '_blank',
        };
    });
    await takeScreenshot(page, join(folder, `before-${index}.jpg`));
    const click = !missing && geometry.interactive && geometry.type !== 'file' && !geometry.external && !geometry.newTab && mode !== 'focus';
    if (click) {
        if (geometry.submit && !['chat', 'submit'].includes(mode)) {
            // Let the real browser perform validation and focus the control.
            // Checkout and provider requests remain simulations.
            await handle.evaluate((element) => element.form?.addEventListener('submit', (event) => event.preventDefault(), { once: true, capture: true }));
        }
        await page.mouse.click(geometry.x, geometry.y);
        await delay(mode === 'submit' ? 500 : 270);
        if (mode === 'select-slot') {
            const slot = await page.waitForSelector('main [role="listbox"] button', { timeout: 2000 });
            await slot.click();
        }
    }
    if (value) {
        const field = await page.evaluateHandle(() => {
            const selector = 'input:not([type=hidden],[type=file],[type=password],[type=checkbox],[type=radio]):not([readonly]), textarea';
            const visible = (element) => element.getClientRects().length && element.getBoundingClientRect().bottom > 0 && element.getBoundingClientRect().top < innerHeight;
            const active = document.activeElement;
            if (active?.matches(selector)) return active;
            const form = active?.closest('form') || document.querySelector('[role=dialog]') || document.querySelector('main');
            return [...(form?.querySelectorAll(selector) || [])].find(visible) ?? null;
        });
        const input = field.asElement();
        if (input) {
            await input.click({ clickCount: 3 });
            await page.keyboard.type(value, { delay: 15 });
            await delay(150);
        }
        await field.dispose();
    }
    await takeScreenshot(page, join(folder, `after-${index}.jpg`));
    await handle.dispose();
    return { ...geometry, click, missing };
}

function composition(module, key, shots) {
    const seconds = 8;
    const clips = shots.flatMap(({ x, y, click }, index) => {
        const [title, description] = module.steps[index];
        const clip = (suffix, start, duration, image, cursorX, cursorY, ring = false) =>
            `<section id="step-${index}-${suffix}" class="clip" data-start="${start}" data-duration="${duration}" data-track-index="1">
                <img src="./${image}-${index}.jpg" alt="" width="1280" height="720">
                <span class="cursor${ring ? ' click' : ''}" style="left:${cursorX}px;top:${cursorY}px"></span>
                <span class="simulation">Simulación · Datos ficticios</span>
                <footer><p>${escape(module.title)} · Paso ${index + 1} de ${shots.length}</p><h1>${escape(title)}</h1><p>${escape(description)}</p></footer>
            </section>`;
        const start = index * seconds;
        return [
            clip('first', start, .5, 'before', Math.max(9, x - 68), Math.max(10, y - 35)),
            clip('point', start + .5, .5, 'before', x, y, click),
            clip('result', start + 1, seconds - 1, 'after', x, y),
        ];
    }).join('\n');

    return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${escape(module.title)}</title><style>
        *{box-sizing:border-box}body{margin:0;background:#171020;color:#fff;font-family:Arial,sans-serif}
        main{position:relative;width:1280px;height:900px;overflow:hidden}.clip{position:absolute;inset:0}
        img{display:block}.simulation{position:absolute;top:14px;left:246px;border:1px solid #dcd0ef;border-radius:6px;background:#241537;color:#fff;font-size:17px;font-weight:bold;padding:5px 9px}
        footer{height:180px;padding:15px 28px;border-top:3px solid #d164f1;background:#21152e}
        footer p{margin:0;color:#f1dfef;font-size:23px;line-height:1.28}footer p:first-child{font-size:15px;color:#dfb3f0}
        h1{margin:5px 0 8px;font-size:31px}.cursor{position:absolute;width:25px;height:33px;background:#15111c;clip-path:polygon(0 0,100% 64%,58% 67%,74% 91%,52% 100%,36% 73%,5% 91%);filter:drop-shadow(0 0 1px white)}
        .cursor::after{content:'';position:absolute;inset:2px;background:white;clip-path:inherit}
        .cursor.click{clip-path:none;background:transparent}.cursor.click::after{clip-path:polygon(0 0,100% 64%,58% 67%,74% 91%,52% 100%,36% 73%,5% 91%)}
        .cursor.click::before{content:'';position:absolute;left:-18px;top:-18px;width:43px;height:43px;border:5px solid #e486ff;border-radius:100%;box-shadow:0 0 0 2px #32133d}
        </style></head><body><main data-composition-id="velvet-${key}" data-start="0" data-duration="${shots.length * seconds}" data-width="1280" data-height="900" data-no-timeline>${clips}</main></body></html>`;
}

try {
    await waitForServer();
    browser = await puppeteer.launch({ executablePath: required('VELVET_CHROME'), headless: true, pipe: true });
    const page = await browser.newPage();
    await page.setViewport(viewport);
    await page.setRequestInterception(true);
    page.on('request', (request) => {
        const url = new URL(request.url());
        if (url.origin !== origin && !['blob:', 'data:'].includes(url.protocol)) return request.abort();
        if (url.pathname === '/ai/chat' && request.method() === 'POST') {
            return request.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ message: 'Ejemplo: abre Sesiones para comparar el tiempo conectado con el tiempo de transmisión.' }) });
        }
        return request.continue();
    });
    await page.evaluateOnNewDocument(() => {
        localStorage.setItem('velvet-theme', 'dark');
        // El perfil de demostración ya eligió solo las cookies esenciales.
        localStorage.setItem('velvet-cookie-consent', 'accepted');
        localStorage.setItem('velvet-cookie-preferences', JSON.stringify({
            essential: true, analytics: false, marketing: false, personalization: false,
        }));
        // Remove only the intro splash in the recording browser.
        const addStyle = () => {
            const style = document.createElement('style');
            style.textContent = '.apply-intro-overlay { display: none !important }';
            document.documentElement.append(style);
        };
        document.addEventListener('DOMContentLoaded', addStyle, { once: true });
    });

    if (process.env.VELVET_SUPPORT_CHECK === '1') await checkSupport(page);
    if (process.env.VELVET_VIDEO_CHECK === '1') await checkVideo(page);

    for (const key of keys) {
        const module = modules[key];
        if (!module || scenes[key]?.length !== module.steps.length) throw new Error(`Faltan escenas para ${key}`);
        const folder = join(workspace, key);
        mkdirSync(folder);
        const role = module.url.startsWith('/monitor/') ? 'monitor' : module.url.startsWith('/model/') ? 'model' : 'admin';
        await browser.setCookie({ name: 'velvet_demo_role', value: role, url: origin });
        await go(page, module.url);
        const shots = [];
        for (const [index, scene] of scenes[key].entries()) {
            try {
                shots.push(await captureStep(page, folder, index, scene, module.url));
            } catch (error) {
                if (error.code === 'ERR_ASSERTION') throw error;
                console.warn(`${key} · paso ${index + 1}: ${error.message}`);
                await go(page, module.url);
                shots.push(await captureStep(page, folder, index, ['main h1'], module.url));
                shots[index].missing = true;
            }
        }
        writeFileSync(join(folder, 'index.html'), composition(module, key, shots));
        console.log(`${key} · ${shots.length} escenas · objetivos pendientes: ${shots.map((shot, index) => shot.missing ? index + 1 : null).filter(Boolean).join(', ') || 'ninguno'}`);
    }
} finally {
    if (browser) await browser.close();
    server.kill();
}

if (process.env.VELVET_CAPTURE_ONLY !== '1') {
    await renderGuides([workspace]);
}
