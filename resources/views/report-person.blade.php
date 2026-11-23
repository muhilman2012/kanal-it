@extends('layout')
@section('title','Laporan · '.$author)
@section('content')
<section class="heading"><div><a class="crumb" href="{{ url('/laporan').($month ? '?month='.$month : '') }}">Laporan harian</a><h1>{{ $author }}</h1><p>{{ $employee->position ?? 'Pelapor manual' }} · {{ $reports->total() }} laporan</p></div><button class="primary" data-open="create">Tulis laporan</button></section>
@include('report-month')
<div class="list">@forelse($reports as $row)
<article class="report"><div class="row"><strong>{{ \Illuminate\Support\Carbon::parse($row->report_date)->locale('id')->isoFormat('D MMMM YYYY') }}</strong><span>{{ $row->attendance }}</span></div><p>{{ $row->body }}</p>
@foreach(['progress'=>'Status','obstacle'=>'Kendala','initiative'=>'Inisiatif / Permintaan','note'=>'Keterangan'] as $key=>$label)
@if($row->$key)<div>{{ $label }} · {{ $row->$key }}</div>@endif
@endforeach
<div class="actions"><button data-open="edit-{{ $row->id }}">Edit</button><form method="post" action="/laporan/{{ $row->id }}" onsubmit="return confirm('Hapus laporan harian ini?')">@csrf @method('delete')<button>Hapus</button></form></div>
@if(!$employee && $employees->isNotEmpty())<details><summary>Kaitkan ke PDF pegawai</summary><form method="post" action="/laporan/{{ $row->id }}/pegawai">@csrf<label>Pegawai<select name="employee_id" required><option value="">Pilih pegawai</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></label><button>Simpan pilihan</button></form></details>@endif
</article><dialog id="edit-{{ $row->id }}"><h2>Edit laporan</h2>@include('report-fields',['row'=>$row])</dialog>
@empty<p class="empty">Belum ada laporan pada periode ini.</p>@endforelse</div>
@include('pager',['items'=>$reports])
<dialog id="create"><h2>Tulis laporan</h2>@include('report-fields',['row'=>null])</dialog>
@endsection