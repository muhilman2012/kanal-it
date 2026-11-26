@extends('layout')
@section('title','Bot laporan')
@section('content')
<a class="crumb" href="/laporan">Laporan harian</a><h1>Bot WhatsApp</h1>
<section class="cards"><div class="card"><h2>Koneksi WhatsApp</h2><p>{{ $state['status'] }}</p>
@if(!empty($state['qr']))<p>Di ponsel nomor bot, buka Perangkat tertaut dan pindai kode ini.</p><img id="wa-qr" src="/laporan/bot/qr?v={{ $state['qrAt'] }}" alt="Kode QR penautan akun WhatsApp" width="240" height="240"><script>setInterval(()=>document.getElementById('wa-qr').src='/laporan/bot/qr?v='+Date.now(),10000);</script>@endif
<div class="actions"><a href="/laporan/bot">Perbarui status</a><form method="post" action="/laporan/bot/lepas" onsubmit="return confirm('Lepas WhatsApp dari Kanal IT? Nomor bot harus memindai QR lagi.')">@csrf<button type="submit" class="danger">Lepas WhatsApp</button></form></div></div>
<div class="card"><h2>PDF bulanan</h2><form action="/laporan/bulanan"><label>Pegawai<select name="employee" required><option value="">Pilih pegawai</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }} · {{ $e->position }}</option>@endforeach</select></label><label>Bulan<input type="month" name="month" value="{{ now('Asia/Jakarta')->format('Y-m') }}" required></label><button class="primary">Unduh PDF</button></form></div></section>
<details class="panel" style="max-width:none;margin:18px 0"><summary>Cara mengirim laporan dan meminta PDF</summary><p>Kirim aktivitas melalui chat pribadi atau tandai bot di grup yang diizinkan. Perintah seperti /penting dan /cuti bisa dikirim tanpa mention. Tulis satu laporan per tanggal. Jika tanggal tidak lengkap, sistem memakai tanggal pesan dalam WIB.</p><pre style="white-space:pre-wrap">23 November 2026
1. Memperbaiki jaringan ruang rapat
2. Memeriksa koneksi server
Kendala : Menunggu konfirmasi teknisi
Status: Selesai
Jenis: Permintaan</pre><p>Kolom tambahan menerima baris <b>Kendala:</b> dan <b>Keterangan:</b>. Laporan kegiatan otomatis mencatat kehadiran Masuk. Untuk absensi, kirim <code>/cuti 25 nov 2026</code>, <code>/izin 25 nov 2026</code>, atau <code>/sakit 25 nov 2026</code>. Kirim <code>/penting</code> untuk melihat postingan tim. Untuk mengunduh PDF sendiri, kirim <code>/pdf 2026-11</code> lewat chat pribadi. Perintah <code>/pdf</code> memakai bulan berjalan.</p></details><h2>Grup yang diizinkan</h2><section class="list">
@forelse(collect($state['groups']??[])->pluck('name','id')->union(array_combine($enabled,$enabled)) as $jid=>$name)
<form class="entry" method="post" action="/laporan/bot/grup">@csrf<strong>{{ $name }}</strong><input type="hidden" name="jid" value="{{ $jid }}"><input type="hidden" name="enabled" value="{{ in_array($jid,$enabled) ? 0 : 1 }}"><button>{{ in_array($jid,$enabled) ? 'Nonaktifkan' : 'Izinkan grup' }}</button></form>
@empty<p class="empty">Grup akan muncul setelah nomor bot tertaut dan bergabung ke grup.</p>@endforelse</section>
<section class="heading"><h2>Pegawai</h2><div class="actions"><a href="/akses">Atur akun login &amp; izin</a><button class="primary" data-open="add">Tambah pegawai</button></div></section>
<div class="list">@forelse($employees as $e)<article class="entry"><div><strong>{{ $e->name }}</strong><div class="small">{{ $e->position }} · +{{ $e->phone }} · {{ $e->active ? 'Aktif' : 'Nonaktif' }} · {{ in_array($e->id,$linkedEmployees) ? 'Punya akun login' : 'Belum punya akun login' }}</div></div><button data-open="emp-{{ $e->id }}">Edit</button></article><dialog id="emp-{{ $e->id }}"><h2>Edit pegawai</h2>@include('employee-form',['e'=>$e])</dialog>@empty<p class="empty">Daftarkan nama dan nomor WhatsApp pegawai yang boleh mengirim laporan.</p>@endforelse</div><dialog id="add"><h2>Tambah pegawai</h2>@include('employee-form',['e'=>null])</dialog>
@endsection
