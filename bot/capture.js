const puppeteer = require('puppeteer');
const dns = require('node:dns').promises;
const net = require('node:net');

const api = process.env.LOBBY_CAPTURE_API;
const token = process.env.LOBBY_CAPTURE_TOKEN;
if (!api || !token || token.length < 32) throw new Error('Konfigurasi tangkapan layar belum lengkap');

function publicAddress(address) {
    const family = net.isIP(address);
    if (family === 4) {
        const n = address.split('.').map(Number);
        return !(n[0] === 0 || n[0] === 10 || n[0] === 127 || n[0] >= 224 ||
            (n[0] === 100 && n[1] >= 64 && n[1] <= 127) ||
            (n[0] === 169 && n[1] === 254) || (n[0] === 172 && n[1] >= 16 && n[1] <= 31) ||
            (n[0] === 192 && n[1] === 168) || (n[0] === 192 && n[1] === 0) ||
            (n[0] === 198 && (n[1] === 18 || n[1] === 19)));
    }
    if (family === 6) {
        const s = address.toLowerCase();
        if (s.startsWith('::ffff:')) return publicAddress(s.slice(7));
        return !(s === '::' || s === '::1' || s.startsWith('fc') || s.startsWith('fd') ||
            /^fe[89ab]/.test(s) || s.startsWith('2001:db8:'));
    }
    return false;
}

async function safeUrl(value) {
    const url = new URL(value);
    if (url.protocol !== 'https:' || url.port || url.username || url.password) throw new Error('URL HTTPS tidak valid');
    const addresses = await dns.lookup(url.hostname, {all: true});
    if (!addresses.length || addresses.some(row => !publicAddress(row.address))) throw new Error('Alamat jaringan tujuan tidak diizinkan');
    return url.href;
}

async function request(path, options = {}) {
    const response = await fetch(api + path, {
        ...options,
        headers: {Authorization: 'Bearer ' + token, ...(options.headers || {})},
        signal: AbortSignal.timeout(30000),
    });
    if (!response.ok) throw new Error('API tangkapan layar: HTTP ' + response.status);
    return response.json();
}

async function capture(item) {
    let browser;
    try {
        const url = await safeUrl(item.url);
        browser = await puppeteer.launch({
            executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || '/usr/bin/chromium',
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
        });
        const page = await browser.newPage();
        await page.setViewport({width: 1920, height: 1080, deviceScaleFactor: 1});
        await page.setRequestInterception(true);
        page.on('request', request => {
            safeUrl(request.url()).then(() => request.continue().catch(() => {}))
                .catch(() => request.abort().catch(() => {}));
        });
        const response = await page.goto(url, {waitUntil: 'domcontentloaded', timeout: 25000});
        if (!response || response.status() >= 400) throw new Error('Halaman memberi HTTP ' + (response?.status() || 0));
        await new Promise(resolve => setTimeout(resolve, 4000));
        const png = await page.screenshot({type: 'png'});
        if (png.length > 10 * 1024 * 1024) throw new Error('Gambar melebihi 10 MB');
        const form = new FormData();
        form.set('image', new Blob([png], {type: 'image/png'}), 'capture.png');
        await request('/' + item.id, {method: 'POST', body: form});
        process.stdout.write('Tangkapan layar tersimpan untuk tayangan ' + item.id + '\n');
    } catch (error) {
        const message = String(error.message || error).slice(0, 300);
        process.stderr.write('Tayangan ' + item.id + ': ' + message + '\n');
        await request('/' + item.id + '/failed', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({error: message}),
        }).catch(() => {});
    } finally {
        await browser?.close().catch(() => {});
    }
}

async function loop() {
    while (true) {
        try {
            const result = await request('');
            if (result.item) await capture(result.item);
        } catch (error) {
            process.stderr.write(String(error.message || error) + '\n');
        }
        await new Promise(resolve => setTimeout(resolve, 30000));
    }
}

if (require.main === module) loop();
module.exports = {publicAddress, safeUrl};
