@extends('layout')
@section('title','Laporan · '.$author)
@section('content')
<section class="heading"><div><a class="crumb" href="{{ url('/laporan').($month ? '?month='.$month : '') }}">Laporan harian</a><h1>{{ $author }}</h1><p>{{ $employee->position ?? 'Pelapor manual' }} · {{ $reports->total() }} laporan</p></div><button class="primary" data-open="create">Tulis laporan</button></section>
@include('report-month')
<div class="table-scroll"><table class="data-table report-table"><thead><tr><th>Tanggal</th><th>Kehadiran</th><th>Aktivitas</th><th>Status</th><th>Kendala</th><th>Inisiatif / Permintaan</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
@forelse($reports as $row)<tr><td class="date-cell">{{ \Illuminate\Support\Carbon::parse($row->report_date)->format('d-m-Y') }}</td><td>{{ $row->attendance }}</td><td class="report-content">{{ $row->body ?: '—' }}</td><td>{{ $row->progress ?: '—' }}</td><td>{{ $row->obstacle ?: '—' }}</td><td>{{ $row->initiative ?: '—' }}</td><td>{{ $row->note ?: '—' }}</td><td><div class="table-actions"><button data-open="edit-{{ $row->id }}">Edit</button><form method="post" action="/laporan/{{ $row->id }}" onsubmit="return confirm('Hapus laporan harian ini?')">@csrf @method('delete')<button>Hapus</button></form></div>
@if(!$employee && $employees->isNotEmpty() && auth()->user()->hasMenuPermission('report_bot'))<details><summary>Kaitkan ke PDF pegawai</summary><form method="post" action="/laporan/{{ $row->id }}/pegawai">@csrf<label>Pegawai<select name="employee_id" required><option value="">Pilih pegawai</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></label><button>Simpan pilihan</button></form></details>@endif</td></tr>
@empty<tr><td colspan="8" class="table-empty">Belum ada laporan pada periode ini.</td></tr>@endforelse
</tbody></table></div>
@foreach($reports as $row)<dialog id="edit-{{ $row->id }}"><h2>Edit laporan</h2>@include('report-fields',['row'=>$row])</dialog>@endforeach
@include('pager',['items'=>$reports])
<dialog id="create"><h2>Tulis laporan</h2>@include('report-fields',['row'=>null])</dialog>
@endsection
