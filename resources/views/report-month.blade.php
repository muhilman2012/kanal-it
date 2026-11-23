<form class="report-tools" method="get" action="/laporan">
@foreach(request()->only(['employee','author']) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<label>Pilih bulan<input type="month" name="month" value="{{ $month }}"></label><button>Tampilkan</button>
@if($month)<a href="{{ request()->fullUrlWithoutQuery(['month','page']) }}">Semua bulan</a>@endif
</form>