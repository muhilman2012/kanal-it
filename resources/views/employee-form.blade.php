<form method="post" action="/laporan/bot/pegawai{{ $e ? '/'.$e->id : '' }}">@csrf
<label>Nama lengkap<input name="name" maxlength="100" value="{{ $e->name ?? '' }}" required></label>
<label>Posisi / jabatan<input name="position" maxlength="100" value="{{ $e->position ?? '' }}" required></label>
<label>Nomor WhatsApp<input type="tel" name="phone" value="{{ $e->phone ?? '' }}" placeholder="08…" required></label>
<label><input style="display:inline;width:auto" type="checkbox" name="active" value="1" @checked(!$e || $e->active)> Pegawai aktif</label><button class="primary">Simpan</button> <button type="button" data-close>Batal</button></form>