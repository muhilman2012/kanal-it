const stage = document.querySelector('#stage');
const playlistUrl = document.body.dataset.playlistUrl;
const soundButton = document.querySelector('#sound');
const fullscreenButton = document.querySelector('#fullscreen');
let items = [];
let currentId = null;
let timer = null;
let soundEnabled = false;

function waiting(message) {
    stage.replaceChildren();
    const box = document.createElement('div');
    box.className = 'waiting';
    const logo = document.createElement('img');
    logo.src = '/setneg.png';
    logo.alt = '';
    const title = document.createElement('h1');
    title.textContent = 'Layar Lobby';
    const note = document.createElement('p');
    note.textContent = message;
    box.append(logo, title, note);
    stage.append(box);
}

function next() {
    if (!items.length) return waiting('Belum ada tayangan aktif.');
    const index = items.findIndex(item => item.id === currentId);
    play(items[(index + 1) % items.length]);
}

function mediaFailed(item) {
    clearTimeout(timer);
    waiting('Media tidak dapat diputar. Mencoba tayangan berikutnya…');
    timer = setTimeout(() => items.length > 1 ? next() : play(item), items.length > 1 ? 1200 : 15000);
}

function play(item) {
    clearTimeout(timer);
    stage.querySelector('video')?.pause();
    currentId = item.id;
    stage.replaceChildren();
    let element;
    if (item.kind === 'image') {
        element = document.createElement('img');
        element.src = item.url;
        element.alt = '';
        element.onerror = () => mediaFailed(item);
    } else if (item.kind === 'video') {
        element = document.createElement('video');
        element.src = item.url;
        element.autoplay = true;
        element.playsInline = true;
        element.muted = !soundEnabled;
        element.preload = 'auto';
        element.onended = next;
        element.onerror = () => mediaFailed(item);
        soundButton.hidden = soundEnabled;
    } else {
        element = document.createElement('iframe');
        element.src = item.url;
        element.title = 'Laman tayangan lobby';
        element.setAttribute('sandbox', 'allow-scripts allow-same-origin allow-forms allow-popups');
        element.setAttribute('allow', 'autoplay; fullscreen');
        element.referrerPolicy = 'no-referrer';
    }
    if (item.kind !== 'video') soundButton.hidden = true;
    if (item.fit === 'cover') element.classList.add('cover');
    stage.append(element);
    if (item.kind === 'video') {
        element.play().catch(() => {
            element.muted = true;
            soundEnabled = false;
            soundButton.hidden = false;
            element.play().catch(() => mediaFailed(item));
        });
    }
    if (item.duration > 0) timer = setTimeout(next, item.duration * 1000);
}

async function refresh() {
    try {
        const response = await fetch(playlistUrl, {cache: 'no-store'});
        if (response.status === 403 || response.status === 404) {
            items = [];
            currentId = null;
            clearTimeout(timer);
            stage.querySelector('video')?.pause();
            waiting('Akses layar tidak aktif. Periksa alamat dan pengaturan layar.');
            return;
        }
        if (!response.ok) throw new Error('Playlist tidak tersedia');
        const data = await response.json();
        const previous = items;
        items = data.items;
        if (!items.length) {
            currentId = null;
            clearTimeout(timer);
            waiting('Belum ada tayangan aktif.');
        } else if (!previous.length || !items.some(item => item.id === currentId)) {
            play(items[0]);
        }
    } catch {
        if (!items.length) waiting('Koneksi ke Kanal IT terputus. Mencoba lagi…');
    }
}

soundButton.addEventListener('click', () => {
    soundEnabled = true;
    const video = stage.querySelector('video');
    if (video) {
        video.muted = false;
        video.play().catch(() => {});
    }
    soundButton.hidden = true;
});
fullscreenButton.addEventListener('click', () => document.documentElement.requestFullscreen?.());
refresh();
setInterval(refresh, 15000);
