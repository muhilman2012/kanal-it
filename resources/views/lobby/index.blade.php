@extends('layout')
@section('title','Layar Lobby')
@section('content')
<section class="heading"><div><a class="crumb" href="/">Beranda</a><h1>Layar Lobby</h1><p>Atur konten layar, urutan playlist, dan jadwal tayang dalam WIB.</p></div><button class="primary" data-open="screen-add">Tambah layar</button></section>

<div class="lobby-admin">
    <aside class="card">
        <h2>Daftar layar</h2>
        @forelse($screens as $screen)
            <a class="lobby-screen-link {{ $selected?->id === $screen->id ? 'is-selected' : '' }}" href="/lobby?screen={{ $screen->admin_code }}">
                <strong>{{ $screen->name }}</strong>
                <span class="small">{{ $screen->location ?: 'Lokasi belum diisi' }} · {{ $screen->items_count }} tayangan</span>
                <span class="small">{{ !$screen->active ? 'Nonaktif' : ($screen->last_seen_at?->gt(now()->subMinutes(2)) ? 'Online' : 'Belum tersambung') }}</span>
            </a>
        @empty
            <p class="small">Belum ada layar. Tambahkan layar untuk mendapatkan alamat player TV.</p>
        @endforelse
    </aside>

    <div>
        @if($selected)
            <section class="card">
                <div class="heading lobby-card-heading"><div><h2>{{ $selected->name }}</h2><p>{{ $selected->location ?: 'Lokasi belum diisi' }} · {{ $selected->active ? 'Aktif' : 'Nonaktif' }}</p></div><div class="lobby-actions"><button data-open="screen-edit">Edit layar</button><a class="lobby-button" href="{{ $selected->playerUrl() }}" target="_blank" rel="noopener noreferrer">Buka player</a></div></div>
                <label>Alamat pendek untuk browser TV<input readonly value="{{ $selected->playerUrl() }}" aria-label="Alamat layar lobby"></label>
                <div class="lobby-actions"><button type="button" data-user="{{ $selected->playerUrl() }}">Salin alamat</button><form method="post" action="/lobby/screens/{{ $selected->admin_code }}/rotate" onsubmit="return confirm('URL lama pada TV akan berhenti berfungsi. Buat URL baru?')">@csrf<button>Ganti token akses</button></form></div>
                <p class="small" style="margin-top:12px">Ketik kode 8 karakter pada TV atau salin alamat ini. Alamat lama tetap berlaku sampai token akses diganti. Player memperbarui playlist otomatis. Tekan F11 untuk mode layar penuh. Video dimulai tanpa suara; tekan Aktifkan suara pada player TV agar video berikutnya bersuara.</p>
            </section>

            <section class="heading" style="margin-top:28px"><div><h2>Urutan tayangan</h2><p>{{ count($playing) }} tayangan aktif sekarang · {{ $now->format('d/m/Y H:i') }} WIB</p></div><button class="primary" data-open="item-add">Tambah tayangan</button></section>
            <div class="list">
                @forelse($items as $item)
                    <article class="lobby-item">
                        <div class="lobby-item-order">
                            <strong>{{ $loop->iteration }}</strong>
                            <div class="lobby-move">
                                @unless($loop->first)<form method="post" action="/lobby/screens/{{ $selected->admin_code }}/items/{{ $item->id }}/move">@csrf<input type="hidden" name="direction" value="up"><button title="Naikkan urutan" aria-label="Naikkan {{ $item->title }}">↑</button></form>@endunless
                                @unless($loop->last)<form method="post" action="/lobby/screens/{{ $selected->admin_code }}/items/{{ $item->id }}/move">@csrf<input type="hidden" name="direction" value="down"><button title="Turunkan urutan" aria-label="Turunkan {{ $item->title }}">↓</button></form>@endunless
                            </div>
                        </div>
                        <div class="lobby-item-main">
                            <h3>{{ $item->title }}</h3>
                            <p class="small">{{ ['image'=>'Gambar','video'=>'Video','website'=>'Laman web langsung','webshot'=>'Tangkapan layar web'][$item->kind] ?? $item->kind }} · {{ $item->duration_seconds ? $item->duration_seconds.' detik' : 'sampai video selesai' }} · {{ $item->fit === 'cover' ? 'Penuhi layar' : 'Tampilkan utuh' }}</p>
                            <p class="small">{{ $item->mode === 'always' ? 'Tayang tetap dalam rotasi' : 'Periodik'.($item->exclusive ? ' · Ambil alih playlist' : '') }} · {{ !$item->active ? 'Nonaktif' : (in_array($item->id,$playing) ? 'Sedang masuk playlist' : 'Di luar jadwal') }}</p>
                            @if($item->mode === 'scheduled')<p class="small">Hari: {{ $item->weekdays ? collect($item->weekdays)->map(fn($day) => [1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'][$day] ?? $day)->join(', ') : 'Setiap hari' }} · Jam: {{ $item->starts_at ?: '00:00' }}–{{ $item->ends_at ?: '24:00' }} WIB · Tanggal: {{ $item->starts_on?->format('d/m/Y') ?: 'bebas' }} s.d. {{ $item->ends_on?->format('d/m/Y') ?: 'bebas' }}</p>@endif
                            <p class="small">{{ in_array($item->kind, ['website','webshot']) ? $item->website_url : $item->file_name }}</p>
                            @if($item->kind === 'webshot')<p class="small">Diperbarui setiap {{ $item->snapshot_interval_minutes }} menit · {{ $item->snapshot_at ? 'Terakhir '.$item->snapshot_at->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : 'Menunggu tangkapan pertama' }}</p>@if($item->snapshot_error)<p class="small" style="color:#a33131">Gagal mengambil gambar: {{ $item->snapshot_error }}</p>@endif @endif
                        </div>
                        <div class="lobby-actions"><button data-open="item-{{ $item->id }}">Edit</button><form method="post" action="/lobby/screens/{{ $selected->admin_code }}/items/{{ $item->id }}" onsubmit="return confirm('Hapus tayangan ini?')">@csrf @method('delete')<button class="danger">Hapus</button></form></div>
                    </article>
                    <dialog id="item-{{ $item->id }}" class="lobby-dialog"><h2>Edit tayangan</h2>@include('lobby.item-form',['item'=>$item,'screen'=>$selected])</dialog>
                @empty
                    <p class="empty">Belum ada tayangan. Tambahkan gambar, video, atau laman web.</p>
                @endforelse
            </div>
            <dialog id="item-add" class="lobby-dialog"><h2>Tambah tayangan</h2>@include('lobby.item-form',['item'=>null,'screen'=>$selected])</dialog>
            <dialog id="screen-edit"><h2>Edit layar</h2>@include('lobby.screen-form',['screen'=>$selected])<form method="post" action="/lobby/screens/{{ $selected->admin_code }}" onsubmit="return confirm('Hapus layar dan semua tayangannya?')">@csrf @method('delete')<button class="danger">Hapus layar</button></form></dialog>
        @else
            <section class="card"><h2>Mulai dengan satu layar</h2><p>Buat layar lobby, lalu tambahkan materi dan buka alamat player pada browser TV. Materi terjadwal akan masuk playlist pada waktunya.</p><button class="primary" data-open="screen-add">Tambah layar</button></section>
        @endif
    </div>
</div>
<dialog id="screen-add"><h2>Tambah layar</h2>@include('lobby.screen-form',['screen'=>null])</dialog>
@endsection
