@extends('layout')
@section('title','Permintaan Zoom #'.($item->code ?: $item->id))
@section('content')
<a href="/zoom" class="crumb">Permintaan Zoom</a>
<h1>Permintaan #{{ $item->code ?: $item->id }}</h1>
<section class="panel" style="max-width:none">
  <h2>{{ $item->agenda }}</h2>
  <p>Status · {{ ucfirst($item->status) }}</p>
  <p>{{ \Carbon\CarbonImmutable::parse($item->starts_at)->format('d/m/Y H:i') }}–{{ \Carbon\CarbonImmutable::parse($item->ends_at)->format('H:i') }} WIB · {{ $item->participants }} peserta</p>
  <p>Pemohon {{ $item->requester_name }} · {{ $item->unit }} · +{{ $item->phone }}</p>
  <p>Email {{ $item->email ?: '—' }} · Co-host {{ $item->cohost ?: '—' }} · Rekaman {{ $item->recording }}</p>
  <p>Keterangan {{ $item->notes ?: '—' }}</p>
  @if($item->join_url)<p><a href="{{ $item->join_url }}">Link peserta</a> · Meeting ID {{ $item->zoom_meeting_id }}</p>@endif
  @if($item->status==='pending')<p>Persetujuan melalui chat pribadi bot dari nomor penyetuju yang terdaftar.</p>@endif
  @if(in_array($item->status,['rejected','failed']) || ($item->status==='approved' && $item->ends_at<now('Asia/Jakarta')->format('Y-m-d H:i:s')))
    <form method="post" action="/zoom/{{ $item->id }}" onsubmit="return confirm('Hapus riwayat ini? Rapat di Zoom tetap ada.')">@csrf @method('delete')<button class="danger">Hapus riwayat</button></form>
  @endif
</section>
@if(in_array($item->status,['pending','approved']))
<section class="card" style="margin:20px 0">
  <h2>Edit detail permintaan</h2>
  <p>{{ $item->status==='approved' ? 'Perubahan judul atau jadwal akan disinkronkan ke rapat Zoom sebelum disimpan. Perubahan lain disimpan di Kanal IT.' : 'Perubahan akan dikirim ulang kepada penyetuju yang terdaftar.' }}</p>
  <form method="post" action="/zoom/{{ $item->id }}" class="zoom-edit-form">@csrf @method('put')
    <label>Tanggal rapat<input type="date" name="date" value="{{ old('date',substr($item->starts_at,0,10)) }}" required></label>
    <div class="zoom-time-fields"><label>Mulai WIB<input type="time" name="start" value="{{ old('start',substr($item->starts_at,11,5)) }}" required></label><label>Selesai WIB<input type="time" name="end" value="{{ old('end',substr($item->ends_at,11,5)) }}" required></label></div>
    <label class="wide">Judul kegiatan<input name="agenda" value="{{ old('agenda',$item->agenda) }}" maxlength="255" required></label>
    <label>Unit kerja<input name="unit" value="{{ old('unit',$item->unit) }}" maxlength="255" required></label>
    <label>Nama PIC<input name="requester_name" value="{{ old('requester_name',$item->requester_name) }}" maxlength="255" required></label>
    <label>Email PIC<input type="email" name="email" value="{{ old('email',$item->email) }}" maxlength="255"></label>
    <label>Jumlah peserta<input type="number" name="participants" min="1" max="100000" value="{{ old('participants',$item->participants) }}" required></label>
    <label>Co-host<input name="cohost" value="{{ old('cohost',$item->cohost) }}" maxlength="255"></label>
    <label>Rekaman<select name="recording" required>@foreach(['Tidak','Audio','Video'] as $recording)<option value="{{ $recording }}" @selected(old('recording',$item->recording)===$recording)>{{ $recording }}</option>@endforeach</select></label>
    <label class="wide">Keterangan<textarea name="notes" maxlength="2000">{{ old('notes',$item->notes) }}</textarea></label>
    <div class="actions"><button class="primary">Simpan perubahan</button></div>
  </form>
</section>
@endif
<h2>Log permintaan dan persetujuan</h2>
<div class="list">@foreach($logs as $log)<div class="entry"><strong>{{ $log->event }}</strong><span>{{ $log->actor }} · {{ $log->created_at }} · {{ $log->detail }}</span></div>@endforeach</div>
@endsection
