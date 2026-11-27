const code = document.body.dataset.code;
const statusLabel = document.querySelector('#status');
const detailLabel = document.querySelector('#detail');
const dot = document.querySelector('#status-dot');
const activateButton = document.querySelector('#activate');
const voiceButton = document.querySelector('#test-voice');
const clock = document.querySelector('#clock');
let enabled = false;
let busy = false;
let audioContext;

function setStatus(title, detail, state = '') {
    statusLabel.textContent = title;
    detailLabel.textContent = detail;
    dot.className = 'dot ' + state;
}

function updateClock() {
    clock.textContent = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Jakarta', dateStyle: 'full', timeStyle: 'medium',
    }).format(new Date()) + ' WIB';
}

async function request(path, body) {
    const response = await fetch('/api/announcer/' + code + path, {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(body || {}), cache: 'no-store',
    });
    if (!response.ok) throw new Error('Server memberi HTTP ' + response.status);
    return response.status === 204 ? null : response.json();
}

async function bell() {
    if (!audioContext) throw new Error('Speaker belum diaktifkan');
    await audioContext.resume();
    const start = audioContext.currentTime;
    for (const [index, frequency] of [880, 660, 880].entries()) {
        const oscillator = audioContext.createOscillator();
        const gain = audioContext.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;
        gain.gain.setValueAtTime(0.0001, start + index * 0.34);
        gain.gain.exponentialRampToValueAtTime(0.16, start + index * 0.34 + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + index * 0.34 + 0.28);
        oscillator.connect(gain).connect(audioContext.destination);
        oscillator.start(start + index * 0.34);
        oscillator.stop(start + index * 0.34 + 0.3);
    }
    await new Promise(resolve => setTimeout(resolve, 1150));
}

function playAudio(url) {
    return new Promise((resolve, reject) => {
        const audio = new Audio(url);
        audio.onended = resolve;
        audio.onerror = () => reject(new Error('Berkas audio gagal diputar'));
        audio.play().catch(() => reject(new Error('Browser memblokir audio. Aktifkan speaker kembali.')));
    });
}

function speak(text) {
    return new Promise((resolve, reject) => {
        if (!('speechSynthesis' in window)) return reject(new Error('Browser tidak mendukung text to speech'));
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 0.92;
        utterance.volume = 1;
        const voice = speechSynthesis.getVoices().find(item => item.lang.toLowerCase().startsWith('id'));
        if (voice) utterance.voice = voice;
        utterance.onend = resolve;
        utterance.onerror = event => reject(new Error('Text to speech gagal: ' + (event.error || 'suara tidak tersedia')));
        speechSynthesis.cancel();
        speechSynthesis.speak(utterance);
    });
}

async function poll() {
    if (!enabled || busy) return;
    busy = true;
    try {
        const result = await request('/next');
        if (!result.event) {
            setStatus('Siap menayangkan', 'Menunggu jadwal atau blast berikutnya.', 'active');
            return;
        }
        const event = result.event;
        setStatus('Sedang memutar: ' + event.title, event.kind === 'speech' ? 'Membacakan pengumuman.' : 'Memutar rekaman audio.', 'active');
        try {
            if (event.bell) await bell();
            if (event.kind === 'speech') await speak(event.text);
            else await playAudio(event.audio_url);
            await request('/complete/' + event.play_id, {success: true});
            setStatus('Selesai: ' + event.title, 'Menunggu jadwal berikutnya.', 'active');
        } catch (error) {
            await request('/complete/' + event.play_id, {success: false, error: String(error.message || error).slice(0, 300)}).catch(() => {});
            setStatus('Pemutaran gagal', String(error.message || error), 'error');
        }
    } catch (error) {
        setStatus('Koneksi terputus', String(error.message || error) + '. Mencoba lagi.', 'error');
    } finally {
        busy = false;
    }
}

activateButton.addEventListener('click', async () => {
    try {
        audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
        await audioContext.resume();
        await bell();
        enabled = true;
        activateButton.hidden = true;
        voiceButton.hidden = false;
        setStatus('Speaker aktif', 'Mengecek jadwal setiap 5 detik.', 'active');
        poll();
    } catch (error) {
        setStatus('Speaker belum aktif', String(error.message || error), 'error');
    }
});
voiceButton.addEventListener('click', () => {
    speak('Uji suara pengumuman kantor.').catch(error => setStatus('Uji suara gagal', error.message, 'error'));
});
updateClock();
setInterval(updateClock, 1000);
setInterval(poll, 5000);
