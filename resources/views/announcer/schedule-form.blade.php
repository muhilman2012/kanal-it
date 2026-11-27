<form method="post" enctype="multipart/form-data" action="/pengumuman/jadwal{{ $schedule ? '/'.$schedule->id : '' }}" data-announcer-schedule-form>@csrf @if($schedule)@method('put')@endif
  <label>Nama materi<input name="title" value="{{ $schedule?->title }}" placeholder="Indonesia Raya / Janji KORPRI / Pancasila" maxlength="150" required></label>
  <label>Rekaman audio (MP3, WAV, OGG, M4A, atau WebM)<input type="file" name="audio" accept="audio/mpeg,audio/wav,audio/ogg,audio/mp4,audio/webm" @if(!$schedule) required @endif></label>
  <p class="small">Maksimal 100 MB. {{ $schedule ? 'Kosongkan untuk mempertahankan audio saat ini: '.$schedule->audio_name : 'Unggah audio yang akan diputar pada jadwal ini.' }}</p>
  <label>Waktu tayang<select name="time_type"><option value="fixed" @selected(!$schedule || $schedule->time_type==='fixed')>Jam tetap</option><option value="before_close" @selected($schedule?->time_type==='before_close')>Sebelum jam pulang</option></select></label>
  <div data-announcer-fixed><label>Jam tayang WIB<input type="time" name="play_time" value="{{ $schedule?->play_time ?? '10:00' }}"></label></div>
  <div data-announcer-close><label>Berapa menit sebelum pulang?<input type="number" name="minutes_before_close" min="1" max="120" value="{{ $schedule?->minutes_before_close ?? 15 }}"></label></div>
  <fieldset class="lobby-schedule"><legend>Hari tayang</legend><div class="lobby-days">@foreach([1=>'Senin',2=>'Selasa',3=>'Rabu',4=>'Kamis',5=>'Jumat',6=>'Sabtu',7=>'Minggu'] as $day=>$label)<label class="checkline"><input type="checkbox" name="weekdays[]" value="{{ $day }}" @checked(in_array($day,$schedule ? (json_decode($schedule->weekdays,true) ?: []) : [1,2,3,4,5]))>{{ $label }}</label>@endforeach</div></fieldset>
  <div class="lobby-form-grid"><label>Mulai berlaku<input type="date" name="starts_on" value="{{ $schedule?->starts_on }}"></label><label>Akhir berlaku<input type="date" name="ends_on" value="{{ $schedule?->ends_on }}"></label></div>
  <label>Urutan jika waktu sama<input type="number" name="sort_order" min="0" max="9999" value="{{ $schedule?->sort_order ?? 10 }}"></label>
  <label class="checkline"><input type="checkbox" name="bell" value="1" @checked($schedule?->bell)> Mainkan nada bel sebelum audio</label>
  <label class="checkline"><input type="checkbox" name="active" value="1" @checked(!$schedule || $schedule->active)> Jadwal aktif</label>
  <div class="actions"><button class="primary">Simpan jadwal</button><button type="button" data-close>Batal</button></div>
</form>
