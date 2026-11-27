<form method="post" enctype="multipart/form-data" action="/lobby/screens/{{ $screen->id }}/items{{ $item ? '/'.$item->id : '' }}">@csrf @if($item)@method('put')@endif
<label>Judul tayangan<input name="title" value="{{ $item?->title }}" maxlength="150" required></label>
<label>Jenis materi<select name="kind" required><option value="image" @selected(!$item || $item->kind === 'image')>Gambar</option><option value="video" @selected($item?->kind === 'video')>Video</option><option value="website" @selected($item?->kind === 'website')>Laman web langsung</option><option value="webshot" @selected($item?->kind === 'webshot')>Laman web (tangkapan layar)</option></select></label>
<label>Unggah gambar atau video<input type="file" name="file" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm"></label>
<p class="small">Gambar JPG, PNG, GIF, WebP atau video MP4, WebM. Maksimal 300 MB. Saat mengedit, biarkan kosong untuk mempertahankan berkas lama dengan jenis yang sama.</p>
<label>URL laman web HTTPS<input type="url" name="website_url" value="{{ $item?->website_url }}" placeholder="https://contoh.go.id/halaman"></label>
<p class="small">Mode langsung membutuhkan izin iframe dari situs. Mode tangkapan layar menampilkan gambar statis halaman dan dapat digunakan untuk situs yang memblokir iframe; interaksi dan video situs tidak ikut tampil.</p>
<label>Perbarui tangkapan layar setiap (menit)<input type="number" name="snapshot_interval_minutes" min="5" max="1440" value="{{ $item?->snapshot_interval_minutes ?? 15 }}"></label>
<label>Penyesuaian gambar/video<select name="fit"><option value="contain" @selected(!$item || $item->fit === 'contain')>Utuh, mungkin ada ruang kosong</option><option value="cover" @selected($item?->fit === 'cover')>Memenuhi layar, tepi mungkin terpotong</option></select></label>
<label>Durasi tayang (detik)<input type="number" name="duration_seconds" min="0" max="3600" value="{{ $item?->duration_seconds ?? 15 }}" required></label>
<p class="small">Untuk video, isi 0 agar lanjut setelah video selesai. Gambar dan laman web harus lebih dari 0 detik.</p>
<label>Pola tayang<select name="mode"><option value="always" @selected(!$item || $item->mode === 'always')>Tetap dalam rotasi</option><option value="scheduled" @selected($item?->mode === 'scheduled')>Periodik sesuai jadwal</option></select></label>
<fieldset class="lobby-schedule"><legend>Jadwal periodik</legend><p class="small">Dipakai hanya jika pola tayang periodik. Kosongkan hari dan tanggal agar berlaku setiap hari tanpa batas tanggal.</p>
<div class="lobby-days">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $day=>$label)<label class="checkline"><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day,$item?->weekdays ?? []))>{{ $label }}</label>@endforeach</div>
<div class="lobby-form-grid"><label>Tanggal mulai<input type="date" name="starts_on" value="{{ $item?->starts_on?->format('Y-m-d') }}"></label><label>Tanggal akhir<input type="date" name="ends_on" value="{{ $item?->ends_on?->format('Y-m-d') }}"></label><label>Jam mulai WIB<input type="time" name="starts_at" value="{{ $item?->starts_at }}"></label><label>Jam akhir WIB<input type="time" name="ends_at" value="{{ $item?->ends_at }}"></label></div>
<label class="checkline"><input type="checkbox" name="exclusive" value="1" @checked($item?->exclusive)> Ambil alih playlist saat jadwal aktif</label></fieldset>
<label class="checkline"><input type="checkbox" name="active" value="1" @checked(!$item || $item->active)> Tayangan aktif</label>
<div class="actions"><button class="primary">Simpan tayangan</button><button type="button" data-close>Batal</button></div>
</form>
