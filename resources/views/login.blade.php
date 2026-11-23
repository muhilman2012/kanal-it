@extends('layout')
@section('title','Masuk')
@section('content')
<div class="login"><section class="poster"><span class="tag">KANAL INTERNAL</span><h1>Ruang kerja<br>tim IT<span>.</span></h1><p>Satu portal untuk seluruh kebutuhan operasional IT.</p>@include('art')</section><form class="loginform" method="post">@csrf<h2>Masuk ke kanal</h2><p>Gunakan akun internal tim IT.</p><label>Email<input name="email" type="email" autocomplete="username" value="{{ old('email') }}" required autofocus></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><button class="primary">Masuk</button></form></div>
@endsection
