<form method="post" enctype="multipart/form-data" action="/penting{{ $p ? '/'.$p->id : '' }}">@csrf
<label>Judul<input name="title" maxlength="120" value="{{ $p->title ?? '' }}" required></label>
<label>Tautan<input type="url" name="url" maxlength="2048" value="{{ $p->url ?? '' }}" placeholder="https://…"></label>
<label>{{ $p?->filename ? 'Ganti lampiran' : 'File atau gambar' }} (maksimal 2 MB)<input type="file" name="file"></label>
<div class="actions"><button class="primary">Simpan</button><button type="button" data-close>Batal</button></div></form>