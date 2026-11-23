@extends('layout')
@section('title','Laporan harian')
@section('content')
<section class="heading"><div><a href="/" class="crumb">Beranda</a><h1>Laporan harian</h1><p>Pilih nama untuk membuka dan mengelola laporan hariannya.</p></div><a href="/laporan/bot">Bot WhatsApp &amp; PDF bulanan</a><button class="primary" data-open="create">Tulis laporan</button></section>
@include('report-month')
<div class="list">
@foreach($employees as $person)
<a class="entry" href="{{ url('/laporan').'?'.http_build_query(array_filter(['employee'=>$person->id,'month'=>$month])) }}" target="_blank" rel="noopener"><div><strong>{{ $person->name }}</strong><div class="small">{{ $person->position }} · Pegawai #{{ $person->id }}</div></div><span>{{ $counts[$person->id] ?? 0 }} laporan</span></a>
@endforeach
@foreach($manual as $person)
<a class="entry" href="{{ url('/laporan').'?'.http_build_query(array_filter(['author'=>$person->author,'month'=>$month])) }}" target="_blank" rel="noopener"><div><strong>{{ $person->author }}</strong><div class="small">Pelapor manual</div></div><span>{{ $person->total }} laporan</span></a>
@endforeach
@if($employees->isEmpty() && $manual->isEmpty())<p class="empty">Belum ada pegawai atau laporan untuk ditampilkan.</p>@endif
</div><dialog id="create"><h2>Tulis laporan</h2>@include('report-fields',['row'=>null])</dialog>
@endsection