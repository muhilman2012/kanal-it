@extends('layout')
@section('title','Permintaan Zoom #'.($item->code ?: $item->id))
@section('content')
<a href="/zoom" class="crumb">Permintaan Zoom</a><h1>Permintaan #{{ $item->code ?: $item->id }}</h1><section class="panel" style="max-width:none"><h2>{{ $item->agenda }}</h2><p>Status · {{ $item->status }}</p><p>{{ $item->starts_at }}–{{ $item->ends_at }} WIB · {{ $item->participants }} peserta</p><p>Pemohon {{ $item->requester_name }} · {{ $item->unit }} · +{{ $item->phone }}</p><p>Email {{ $item->email ?: '—' }} · Co-host {{ $item->cohost ?: '—' }} · Rekaman {{ $item->recording }}</p><p>Keterangan {{ $item->notes ?: '—' }}</p>@if($item->join_url)<p><a href="{{ $item->join_url }}">Link peserta</a> · Meeting ID {{ $item->zoom_meeting_id }}</p>@endif
@if($item->status==='pending')<p>Persetujuan melalui chat pribadi bot dari nomor penyetuju yang terdaftar.</p>@endif</section>
<h2>Log permintaan dan persetujuan</h2><div class="list">@foreach($logs as $log)<div class="entry"><strong>{{ $log->event }}</strong><span>{{ $log->actor }} · {{ $log->created_at }} · {{ $log->detail }}</span></div>@endforeach</div>
@endsection