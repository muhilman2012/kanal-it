@extends('layout')
@section('title','Ganti password')
@section('content')
<section class="heading"><div><a class="crumb" href="/">Beranda</a><h1>Ganti password</h1></div></section><form class="panel" method="post">@csrf @method('put')<label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label><label>Password baru (minimal 12 karakter)<input name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required></label><label>Konfirmasi password baru<input name="password_confirmation" type="password" autocomplete="new-password" required></label><button class="primary">Simpan password</button></form>
@endsection
