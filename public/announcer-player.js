const code = document.body.dataset.code;
const statusLabel = document.querySelector('#status');
const detailLabel = document.querySelector('#detail');
const dot = document.querySelector('#status-dot');
const activateButton = document.querySelector('#activate');
const voiceButton = document.querySelector('#test-voice');
const voiceSelect = document.querySelector('#voice-select');
const voiceHelp = document.querySelector('#voice-help');
const rateInput = document.querySelector('#voice-rate');
const volumeInput = document.querySelector('#voice-volume');
const clock = document.querySelector('#clock');
let enabled = false;
let busy = false;

function savedSetting(name, fallback) {
    try { return localStorage.getItem('announcer-' + name) || fallback; }
    catch { return fallback; }
}

function saveSetting(name, value) {
    try { localStorage.setItem('announcer-' + name, value); } catch { /* Storage may be disabled. */ }
}

rateInput.value = savedSetting('rate', '0.9');
volumeInput.value = savedSetting('volume', '0.8');
function updateVoiceControls() {
    document.querySelector('#rate-value').textContent = Number(rateInput.value).toLocaleString('id-ID') + '×';
    document.querySelector('#volume-value').textContent = Math.round(Number(volumeInput.value) * 100) + '%';
}
for (const [input, name] of [[rateInput, 'rate'], [volumeInput, 'volume']]) {
    input.addEventListener('input', () => { saveSetting(name, input.value); updateVoiceControls(); });
}
updateVoiceControls();

function availableVoices() {
    return 'speechSynthesis' in window ? speechSynthesis.getVoices() : [];
}

function populateVoices() {
    const voices = availableVoices();
    const indonesian = voices.filter(voice => voice.lang.toLowerCase().startsWith('id'));
    const choices = indonesian.length ? indonesian : voices;
    voiceSelect.replaceChildren(new Option('Suara bawaan browser', ''));
    for (const voice of choices) {
        voiceSelect.add(new Option(voice.name + ' (' + voice.lang + ')', voice.voiceURI));
    }
    const saved = savedSetting('voice', '');
    if (choices.some(voice => voice.voiceURI === saved)) voiceSelect.value = saved;
    voiceHelp.textContent = indonesian.length
        ? 'Pilih suara yang tersedia di perangkat ini, lalu dengarkan uji suara. Browser tidak memberi label jenis kelamin suara.'
        : 'Belum ada suara Bahasa Indonesia di browser ini. Suara bahasa lain mungkin salah melafalkan teks; pasang suara Bahasa Indonesia pada perangkat speaker.';
}
voiceSelect.addEventListener('change', () => saveSetting('voice', voiceSelect.value));
if ('speechSynthesis' in window) {
    populateVoices();
    speechSynthesis.addEventListener('voiceschanged', populateVoices);
} else {
    voiceSelect.disabled = true;
    voiceHelp.textContent = 'Browser ini tidak mendukung pembacaan teks.';
}

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
    await playAudio('/announcer-bell.mp3', 0.8);
}

function playAudio(url, volume = 1) {
    return new Promise((resolve, reject) => {
        const audio = new Audio(url);
        audio.volume = volume;
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
        utterance.rate = Number(rateInput.value);
        utterance.volume = Number(volumeInput.value);
        const voice = availableVoices().find(item => item.voiceURI === voiceSelect.value)
            || availableVoices().find(item => item.lang.toLowerCase().startsWith('id'));
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
    voiceButton.disabled = true;
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
        voiceButton.disabled = false;
    }
}

activateButton.addEventListener('click', async () => {
    try {
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
