@extends('layout')
@section('title','Laporan harian')
@section('content')
<section class="heading"><div><a href="/" class="crumb">Beranda</a><h1>Laporan harian</h1><p>Pilih nama untuk membuka dan mengelola laporan hariannya.</p></div>@if(auth()->user()->hasMenuPermission('report_bot'))<a href="/laporan/bot">Bot WhatsApp &amp; pegawai</a>@endif<button class="primary" data-open="create">Tulis laporan</button></section>
@include('report-month')
<div class="table-scroll"><table class="data-table"><thead><tr><th>Tanggal terakhir</th><th>Nama</th><th>Jabatan</th><th>Jumlah laporan</th><th>Aksi</th></tr></thead><tbody>
@forelse($people as $person)<tr><td class="date-cell">{{ $person->last_date ? \Illuminate\Support\Carbon::parse($person->last_date)->format('d-m-Y') : '—' }}</td><td><strong>{{ $person->name }}</strong></td><td>{{ $person->position }}</td><td>{{ $person->total }}</td><td><a href="{{ url('/laporan').'?'.http_build_query(array_filter(['employee'=>$person->employee_id,'author'=>$person->employee_id ? null : $person->name,'month'=>$month])) }}">Buka laporan</a></td></tr>
@empty<tr><td colspan="5" class="table-empty">Belum ada pegawai atau laporan untuk ditampilkan.</td></tr>@endforelse
</tbody></table></div><dialog id="create"><h2>Tulis laporan</h2>@include('report-fields',['row'=>null])</dialog>
@endsection
