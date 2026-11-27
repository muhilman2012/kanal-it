const toast = document.querySelector('#toast');
let timer;
function notify(message) {
    clearTimeout(timer);
    toast.textContent = message;
    timer = setTimeout(() => toast.textContent = '', 3500);
}
document.addEventListener('submit', event => {
    if (event.target.matches('[data-delete]') && !confirm('Hapus akun Office ini?')) event.preventDefault();
});
document.addEventListener('click', async event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.dataset.open) document.getElementById(button.dataset.open).showModal();
    if (button.hasAttribute('data-close')) button.closest('dialog').close();
    if (!button.hasAttribute('data-user') && !button.dataset.secret) return;
    try {
        let value = button.dataset.user;
        if (button.dataset.secret) {
            const response = await fetch('/akun/' + button.dataset.secret + '/salin', {
                method: 'POST', headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json'}, cache: 'no-store'
            });
            if (!response.ok) throw new Error();
            value = (await response.json()).value;
        }
        await navigator.clipboard.writeText(value);
        notify(button.dataset.secret ? 'Password disalin.' : 'Username disalin.');
    } catch {
        notify('Gagal menyalin. Periksa sesi login dan izin clipboard browser.');
    }
});

function updateLobbyItemForm(form) {
    const kind = form.elements.kind.value;
    const scheduled = form.elements.mode.value === 'scheduled';
    const fields = {
        file: kind === 'image' || kind === 'video',
        website: kind === 'website' || kind === 'webshot',
        snapshot: kind === 'webshot',
        fit: kind !== 'website',
        schedule: scheduled,
    };
    for (const [name, visible] of Object.entries(fields)) {
        const section = form.querySelector('[data-lobby-field="' + name + '"]');
        section.hidden = !visible;
        section.querySelectorAll('input, select, textarea').forEach(input => input.disabled = !visible);
    }
    const file = form.elements.file;
    const needsFile = fields.file && (form.dataset.originalKind !== kind || form.dataset.hasFile !== '1');
    file.required = needsFile;
    file.accept = kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/gif,image/webp';
    form.querySelector('[data-lobby-file-label]').textContent = kind === 'video' ? 'Unggah video' : 'Unggah gambar';
    form.querySelector('[data-lobby-file-help]').textContent = kind === 'video'
        ? 'Video MP4 atau WebM, maksimal 300 MB. ' + (needsFile ? 'Pilih video untuk tayangan ini.' : 'Kosongkan untuk mempertahankan video saat ini.')
        : 'Gambar JPG, PNG, GIF, atau WebP, maksimal 300 MB. ' + (needsFile ? 'Pilih gambar untuk tayangan ini.' : 'Kosongkan untuk mempertahankan gambar saat ini.');
    form.elements.website_url.required = fields.website;
    form.querySelector('[data-lobby-website-help]').textContent = kind === 'webshot'
        ? 'Tayangan berupa gambar statis halaman; video dan interaksi situs tidak ikut tampil.'
        : 'Situs harus mengizinkan tampil dalam iframe browser TV.';
    const duration = form.elements.duration_seconds;
    duration.min = kind === 'video' ? '0' : '1';
    if (kind !== 'video' && Number(duration.value) === 0) duration.value = '15';
    form.querySelector('[data-lobby-duration-help]').textContent = kind === 'video'
        ? 'Isi 0 agar video diputar sampai selesai. Isi angka untuk beralih setelah durasi tertentu.'
        : 'Minimal 1 detik sebelum tayangan berikutnya.';
}

document.querySelectorAll('[data-lobby-item-form]').forEach(form => {
    updateLobbyItemForm(form);
    form.elements.kind.addEventListener('change', () => updateLobbyItemForm(form));
    form.elements.mode.addEventListener('change', () => updateLobbyItemForm(form));
});
