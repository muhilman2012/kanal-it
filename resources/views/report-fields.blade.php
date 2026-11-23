<form method="post" action="{{ $row ? '/laporan/'.$row->id : '/laporan' }}">@csrf
@if($row) @method('put') @endif
@isset($author)
<input type="hidden" name="detail" value="1"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="employee_id" value="{{ $employee->id ?? '' }}"><input type="hidden" name="author" value="{{ $author }}">
@else
<label>Pegawai<select name="employee_id"><option value="">Pelapor manual</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></label><label>Nama pelapor manual<input name="author" maxlength="100"></label>
@endisset
<label>Tanggal<input type="date" name="report_date" value="{{ $row->report_date ?? ($month && $month!==now('Asia/Jakarta')->format('Y-m') ? $month.'-01' : now('Asia/Jakarta')->toDateString()) }}" required></label>
<label>Kehadiran<input name="attendance" maxlength="100" value="{{ $row->attendance ?? 'Masuk' }}"></label>
<label>Aktivitas<textarea name="body" rows="3" maxlength="10000">{{ $row->body ?? '' }}</textarea></label>
@foreach(['progress'=>'Status pekerjaan','obstacle'=>'Kendala','initiative'=>'Inisiatif / Permintaan','note'=>'Keterangan'] as $key=>$label)<label>{{ $label }}<input name="{{ $key }}" value="{{ $row->$key ?? '' }}"></label>@endforeach
<div class="actions"><button class="primary">Simpan laporan</button><button type="button" data-close>Batal</button></div></form>