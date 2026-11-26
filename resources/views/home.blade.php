@extends('layout')
@section('title','Beranda')
@section('content')
<section class="hero"><div><span class="tag">KANAL INTERNAL</span><h1>Ruang kerja tim IT<span>.</span></h1><p>Satu portal untuk seluruh kebutuhan operasional IT.</p></div>@include('art')</section><h2>Perangkat tim</h2><div class="cards">@foreach([['/akun','▦','Clipboard Akun','Simpan dan salin kredensial Office.',$accounts.' akun','Buka clipboard'],['/laporan','≡','Laporan harian','Catat aktivitas dan telusuri pekerjaan tim.',$reports.' laporan','Buka laporan']] as [$url,$icon,$name,$desc,$count,$link])<section class="card"><div class="row"><i class="icon">{{ $icon }}</i><span class="small">{{ $count }}</span></div><h3>{{ $name }}</h3><p>{{ $desc }}</p><a href="{{ $url }}">{{ $link }}</a></section>@endforeach</div>
@endsection
