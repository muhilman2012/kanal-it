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
