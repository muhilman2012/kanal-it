@extends('layout')
@section('title','Panduan Bot WhatsApp')
@section('content')
<a class="crumb" href="/">Beranda</a>
<h1>Panduan Bot WhatsApp</h1>
<p>Mulai dengan mengirim <code>/halo</code>. Balasan bot menampilkan perintah sesuai nomor WhatsApp pengirim.</p>

<section class="cards">
    <div class="card">
        <h2>Pegawai terdaftar</h2>
        <p>Nomor pegawai yang aktif dapat mengirim laporan dan memakai perintah berikut.</p>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>Pesan</th><th>Fungsi</th></tr></thead><tbody>
            <tr><td><code>/halo</code></td><td>Melihat menu singkat.</td></tr>
            <tr><td><code>/tutorial</code></td><td>Membaca panduan laporan langsung di WhatsApp.</td></tr>
            <tr><td>Uraian pekerjaan</td><td>Menyimpan laporan harian dengan kehadiran <strong>Masuk</strong>. Jika tanggal lengkap tidak ditulis, bot memakai tanggal pesan dalam WIB.</td></tr>
            <tr><td><code>/cuti 25 nov 2026</code><br><code>/izin 25 nov 2026</code><br><code>/sakit 25 nov 2026</code></td><td>Mencatat status kehadiran pada tanggal yang disebutkan.</td></tr>
            <tr><td><code>/pdf</code><br><code>/pdf 2026-11</code></td><td>Mengunduh PDF laporan sendiri untuk bulan berjalan atau bulan yang dipilih. Gunakan di chat pribadi.</td></tr>
            <tr><td><code>/akun</code><br><code>/akun drive</code></td><td>Menampilkan daftar Clipboard Akun atau mencari berdasarkan nama. Balas nomor dalam daftar untuk membuka detail. Gunakan di chat pribadi.</td></tr>
            <tr><td><code>/penting</code><br><code>/penting 3</code></td><td>Menampilkan daftar postingan penting atau membuka item sesuai nomor.</td></tr>
            <tr><td><code>/zoom</code></td><td>Memulai permintaan ruang Zoom lewat chat pribadi.</td></tr>
        </tbody></table></div>
    </div>
    <div class="card">
        <h2>Pemohon lainnya</h2>
        <p>Nomor yang tidak terdaftar sebagai pegawai aktif mendapat menu <code>/zoom</code> saja ketika mengirim <code>/halo</code>.</p>
        <p>Kirim <code>/zoom</code> lewat chat pribadi bot, lalu jawab pertanyaan satu per satu. Setelah ringkasan tampil, balas <strong>KIRIM</strong> untuk mengajukan atau <code>/batal</code> untuk membatalkan draf.</p>
        <p>Pengajuan diteruskan kepada penyetuju yang terdaftar. Bot akan mengirim hasil permintaan ke nomor pemohon.</p>
        <h3>Penyetuju Zoom</h3>
        <p>Nomor penyetuju yang aktif dapat membalas notifikasi lewat chat pribadi:</p>
        <ul><li><code>/setuju KODE NOMOR_AKUN</code> untuk memilih host dan membuat rapat.</li><li><code>/tolak KODE ALASAN</code> untuk menolak dengan alasan.</li></ul>
        <p class="small">Contoh kode dan nomor akun tercantum pada notifikasi permintaan. Penyetuju prioritas mendapat kesempatan pertama selama 15 menit.</p>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <h2>Contoh laporan</h2>
    <pre class="guide-example">26 November 2026
1. Memeriksa jaringan ruang rapat.
2. Menyiapkan perangkat konferensi.
Status: Selesai
Jenis: Permintaan
Kendala: Menunggu adaptor cadangan
Keterangan: Perangkat sudah diuji.</pre>
    <p>Baris <strong>Kendala</strong>, <strong>Status</strong>, <strong>Jenis</strong>, dan <strong>Keterangan</strong> dapat ditambahkan bila diperlukan. Kirim satu laporan untuk satu tanggal.</p>
    <h3>Penggunaan di grup</h3>
    <p>Bot hanya memproses pesan dari grup yang telah diizinkan. Perintah berawalan <code>/</code>, termasuk <code>/halo</code> dan <code>/penting</code>, tidak perlu mention. Untuk uraian laporan biasa, mention akun bot. Permintaan Zoom, PDF, dan detail Clipboard Akun dikerjakan lewat chat pribadi.</p>
</section>
@endsection
