@extends('layout')
@section('title','Pengumuman Kantor')
@section('content')
<section class="heading"><div><a class="crumb" href="/">Beranda</a><h1>Pengumuman Kantor</h1><p>Jadwal audio, siaran teks otomatis, dan perangkat speaker khusus.</p></div></section>
<div class="cards">
  <section class="card">
    <h2>Player speaker</h2>
    <p>Buka halaman ini pada satu komputer yang terhubung ke sistem pengeras suara kantor. Biarkan browser dan tab tetap terbuka.</p>
    <label>Alamat player khusus<input readonly value="{{ rtrim(config('app.url'),'/').'/speaker/'.$settings->player_code }}"></label>
    <div class="actions"><a class="zoom-filter-link" href="{{ rtrim(config('app.url'),'/').'/speaker/'.$settings->player_code }}" target="_blank" rel="noopener noreferrer">Buka player</a><button type="button" data-user="{{ rtrim(config('app.url'),'/').'/speaker/'.$settings->player_code }}">Salin alamat</button></div>
    <p class="small">Status: {{ $settings->last_seen_at && \Carbon\CarbonImmutable::parse($settings->last_seen_at)->gt(now()->subSeconds(30)) ? 'Terhubung' : 'Belum terhubung' }} · Terakhir terlihat {{ $settings->last_seen_at ? \Carbon\CarbonImmutable::parse($settings->last_seen_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i:s').' WIB' : '—' }}</p>
    <form method="post" action="/pengumuman/ganti-kode" onsubmit="return confirm('URL speaker lama akan berhenti berfungsi. Ganti kode?')">@csrf<button>Ganti kode akses</button></form>
  </section>
  <section class="card">
    <h2>Jam pulang</h2>
    <p>Jadwal sore dihitung dari jam pulang setiap hari. Jadwal Sabtu dan Minggu dapat dibuat dengan jam tetap.</p>
    <form method="post" action="/pengumuman/pengaturan">@csrf @method('put')
      <div class="lobby-form-grid">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat'] as $day=>$label)<label>{{ $label }}<input type="time" name="closing_times[{{ $day }}]" value="{{ $closingTimes[$day] ?? '' }}" required></label>@endforeach</div>
      <button class="primary">Simpan jam pulang</button>
    </form>
  </section>
</div>
<section class="heading" style="margin-top:28px"><div><h2>Jadwal audio berulang</h2><p>Unggah lagu, janji KORPRI, Pancasila, atau rekaman lain. Setiap jadwal diputar sekali per hari yang dipilih.</p></div><button class="primary" data-open="announcer-schedule-add">Tambah jadwal</button></section>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Materi</th><th>Waktu</th><th>Hari</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($schedules as $schedule)
    <tr><td><strong>{{ $schedule->title }}</strong><div class="small">{{ $schedule->audio_name }}</div></td>
      <td>{{ $schedule->time_type==='fixed' ? $schedule->play_time.' WIB' : $schedule->minutes_before_close.' menit sebelum pulang' }}</td>
      <td>{{ collect(json_decode($schedule->weekdays,true) ?: [])->map(fn($day)=>[1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'][$day] ?? $day)->join(', ') }}</td>
      <td>{{ $schedule->active ? 'Aktif' : 'Nonaktif' }}</td>
      <td><div class="table-actions"><button type="button" data-open="announcer-schedule-{{ $schedule->id }}">Edit</button><form method="post" action="/pengumuman/jadwal/{{ $schedule->id }}" onsubmit="return confirm('Hapus jadwal dan audio ini?')">@csrf @method('delete')<button class="danger" style="margin:0">Hapus</button></form></div></td>
    </tr>
  @empty<tr><td colspan="5" class="table-empty">Belum ada jadwal. Tambahkan audio pagi pukul 10.00 atau 15 menit sebelum pulang.</td></tr>@endforelse
</tbody></table></div>
@foreach($schedules as $schedule)<dialog id="announcer-schedule-{{ $schedule->id }}" class="lobby-dialog"><h2>Edit jadwal audio</h2>@include('announcer.schedule-form',['schedule'=>$schedule])</dialog>@endforeach
<dialog id="announcer-schedule-add" class="lobby-dialog"><h2>Tambah jadwal audio</h2>@include('announcer.schedule-form',['schedule'=>null])</dialog>
<div class="cards" style="margin-top:28px">
  <section class="card">
    <h2>Blast teks menjadi suara</h2>
    <p>Nada bel dimainkan dahulu, kemudian teks dibacakan oleh suara pada browser speaker. Kosongkan waktu untuk mengirim sekarang.</p>
    <form method="post" action="/pengumuman/blast">@csrf
      <label>Judul blast<input name="title" maxlength="150" placeholder="Pengumuman mendadak" required></label>
      <label>Teks yang dibacakan<textarea name="text" rows="5" maxlength="2000" required></textarea></label>
      <label>Jadwalkan pada (WIB, opsional)<input type="datetime-local" name="scheduled_at"></label>
      <label class="checkline"><input type="checkbox" name="bell" value="1" checked> Nada bel sebelum pembacaan</label>
      <button class="primary">Jadwalkan blast</button>
    </form>
  </section>
  <section class="card">
    <h2>Antrean dan riwayat blast</h2>
    <div class="approver-list">@forelse($blasts as $blast)<div class="approver-entry"><div><strong>{{ $blast->title }}</strong><div class="small">{{ \Carbon\CarbonImmutable::parse($blast->scheduled_at)->format('d/m/Y H:i') }} WIB · {{ ucfirst($blast->status) }}</div>@if($blast->error)<div class="small">{{ $blast->error }}</div>@endif</div><div class="table-actions">@if($blast->status==='queued')<form method="post" action="/pengumuman/blast/{{ $blast->id }}/batal">@csrf<button>Batal</button></form>@elseif(in_array($blast->status,['failed','playing','expired']))<form method="post" action="/pengumuman/blast/{{ $blast->id }}/ulang">@csrf<button>Ulangi</button></form>@endif</div></div>@empty<p class="small">Belum ada blast.</p>@endforelse</div>
  </section>
</div>
<section class="card" style="margin-top:28px"><h2>Log pemutaran</h2><div class="table-scroll"><table class="data-table"><thead><tr><th>Materi</th><th>Jenis</th><th>Waktu</th><th>Status</th></tr></thead><tbody>@forelse($plays as $play)<tr><td>{{ $play->title }}</td><td>{{ $play->kind==='blast'?'Blast TTS':'Audio terjadwal' }}</td><td>{{ \Carbon\CarbonImmutable::parse($play->claimed_at)->timezone('Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</td><td>{{ ucfirst($play->status) }}{{ $play->error ? ' · '.$play->error : '' }}</td></tr>@empty<tr><td colspan="4" class="table-empty">Belum ada pemutaran.</td></tr>@endforelse</tbody></table></div></section>
@endsection
