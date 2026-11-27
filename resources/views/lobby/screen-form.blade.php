<form method="post" action="/lobby/screens{{ $screen ? '/'.$screen->id : '' }}">@csrf @if($screen)@method('put')@endif
<label>Nama layar<input name="name" value="{{ $screen?->name }}" placeholder="TV Lobby Utama" maxlength="120" required></label>
<label>Lokasi<input name="location" value="{{ $screen?->location }}" placeholder="Lobby lantai 1" maxlength="120"></label>
<label class="checkline"><input type="checkbox" name="active" value="1" @checked(!$screen || $screen->active)> Layar aktif</label>
<div class="actions"><button class="primary">Simpan layar</button><button type="button" data-close>Batal</button></div>
</form>
