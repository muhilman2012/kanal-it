<form method="post" enctype="multipart/form-data" action="/lobby/screens/{{ $screen->admin_code }}/items{{ $item ? '/'.$item->id : '' }}" data-lobby-item-form data-original-kind="{{ $item?->kind }}" data-has-file="{{ $item?->file_path ? '1' : '0' }}">@csrf @if($item)@method('put')@endif
<label>Judul tayangan<input name="title" value="{{ $item?->title }}" maxlength="150" required></label>
<label>Jenis materi<select name="kind" required>
  <option value="image" @selected(!$item || $item->kind === 'image')>Gambar</option>
  <option value="video" @selected($item?->kind === 'video')>Video</option>
  <option value="website" @selected($item?->kind === 'website')>Laman web langsung</option>
  <option value="webshot" @selected($item?->kind === 'webshot')>Laman web (tangkapan layar)</option>
</select></label>
<div data-lobby-field="file">
  <label><span data-lobby-file-label>Unggah gambar</span><input type="file" name="file"></label>
  <p class="small" data-lobby-file-help></p>
</div>
<div data-lobby-field="website">
  <label>URL laman web HTTPS<input type="url" name="website_url" value="{{ $item?->website_url }}" placeholder="https://contoh.go.id/halaman"></label>
  <p class="small" data-lobby-website-help></p>
</div>
<div data-lobby-field="snapshot">
  <label>Perbarui tangkapan layar setiap (menit)<input type="number" name="snapshot_interval_minutes" min="5" max="1440" value="{{ $item?->snapshot_interval_minutes ?? 15 }}"></label>
</div>
<div data-lobby-field="fit">
  <label>Penyesuaian tampilan<select name="fit"><option value="contain" @selected(!$item || $item->fit === 'contain')>Utuh, mungkin ada ruang kosong</option><option value="cover" @selected($item?->fit === 'cover')>Memenuhi layar, tepi mungkin terpotong</option></select></label>
</div>
<label>Durasi tayang (detik)<input type="number" name="duration_seconds" min="0" max="3600" value="{{ $item?->duration_seconds ?? 15 }}" required></label>
<p class="small" data-lobby-duration-help>Minimal 1 detik.</p>
<label>Pola tayang<select name="mode"><option value="always" @selected(!$item || $item->mode === 'always')>Tetap dalam rotasi</option><option value="scheduled" @selected($item?->mode === 'scheduled')>Periodik sesuai jadwal</option></select></label>
<fieldset class="lobby-schedule" data-lobby-field="schedule"><legend>Jadwal periodik</legend><p class="small">Kosongkan hari dan tanggal agar berlaku setiap hari tanpa batas tanggal.</p>
  <div class="lobby-days">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $day=>$label)<label class="checkline"><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day,$item?->weekdays ?? []))>{{ $label }}</label>@endforeach</div>
  <div class="lobby-form-grid"><label>Tanggal mulai<input type="date" name="starts_on" value="{{ $item?->starts_on?->format('Y-m-d') }}"></label><label>Tanggal akhir<input type="date" name="ends_on" value="{{ $item?->ends_on?->format('Y-m-d') }}"></label><label>Jam mulai WIB<input type="time" name="starts_at" value="{{ $item?->starts_at }}"></label><label>Jam akhir WIB<input type="time" name="ends_at" value="{{ $item?->ends_at }}"></label></div>
  <label class="checkline"><input type="checkbox" name="exclusive" value="1" @checked($item?->exclusive)> Ambil alih playlist saat jadwal aktif</label>
</fieldset>
<label class="checkline"><input type="checkbox" name="active" value="1" @checked(!$item || $item->active)> Tayangan aktif</label>
<div class="actions"><button class="primary" type="submit">Simpan tayangan</button><button type="button" data-close>Batal</button></div>
<p class="lobby-saving" role="status" aria-live="polite" hidden><span class="loading-spinner" aria-hidden="true"></span> Menyimpan tayangan. Mohon tunggu sampai selesai…</p>
</form>
