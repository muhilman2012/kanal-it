@extends('layout')
@section('title','Permintaan Zoom')
@section('content')
<section class="heading"><div><a href="/" class="crumb">Beranda</a><h1>Permintaan Zoom</h1><p>Pengajuan, persetujuan, akun host, dan riwayat kegiatan.</p></div></section>
<div class="cards"><section class="card"><div class="row"><h2>Penyetuju</h2><button type="button" class="primary" data-open="add-approver">Tambah</button></div>
<p>Nomor prioritas menerima permintaan lebih dahulu. Jika belum ada keputusan dalam 15 menit, penyetuju aktif lainnya akan diberi notifikasi.</p>
<div class="approver-list">@forelse($approvers as $person)<div class="approver-entry"><div><strong>{{ $person->name }}</strong><div class="small">+{{ $person->phone }}</div></div>@if($person->priority)<span class="priority-badge">★ Prioritas</span>@endif<span class="approver-state {{ $person->active ? 'is-active' : '' }}">{{ $person->active ? 'Aktif' : 'Nonaktif' }}</span><button type="button" data-open="approver-{{ $person->id }}">Edit</button></div>
<dialog id="approver-{{ $person->id }}"><h2>Edit penyetuju</h2><form action="/zoom/approver/{{ $person->id }}" method="post">@csrf @method('put')<label>Nama<input name="name" value="{{ $person->name }}" required></label><label>Nomor WhatsApp<input name="phone" value="{{ $person->phone }}" required></label><label class="checkline"><input type="checkbox" name="active" value="1" @checked($person->active)> Boleh menyetujui permintaan</label><label class="checkline"><input type="checkbox" name="priority" value="1" @checked($person->priority)> Penyetuju prioritas (hanya satu nomor)</label><div class="actions"><button class="primary">Simpan</button><button type="button" data-close>Batal</button></div></form><form action="/zoom/approver/{{ $person->id }}" method="post" onsubmit="return confirm('Hapus penyetuju ini?')">@csrf @method('delete')<button class="danger">Hapus penyetuju</button></form></dialog>
@empty<p class="empty">Belum ada penyetuju.</p>@endforelse</div>
<dialog id="add-approver"><h2>Tambah penyetuju</h2><form action="/zoom/approver" method="post">@csrf<label>Nama<input name="name" required></label><label>Nomor WhatsApp<input name="phone" type="tel" placeholder="0815…" required></label><label class="checkline"><input type="checkbox" name="active" value="1" checked> Boleh menyetujui permintaan</label><label class="checkline"><input type="checkbox" name="priority" value="1"> Jadikan penyetuju prioritas</label><div class="actions"><button class="primary">Simpan</button><button type="button" data-close>Batal</button></div></form></dialog></section>
<section class="card"><div class="row"><h2>Akun Zoom</h2><button type="button" class="primary" data-open="add-zoom-account">Tambah</button></div>
<p>Akun aktif tersedia saat penyetuju memilih host. Kredensial tersimpan tersembunyi dan dapat diperbarui melalui Edit.</p>
<div class="approver-list">@forelse($accounts as $account)<div class="approver-entry"><div><strong>{{ $account->name }}</strong><div class="small">{{ $account->host_email }}</div></div><span class="approver-state {{ $account->active ? 'is-active' : '' }}">{{ $account->active ? 'Aktif' : 'Nonaktif' }}</span><button type="button" data-open="zoom-account-{{ $account->id }}">Edit</button></div>
<dialog id="zoom-account-{{ $account->id }}"><h2>Edit akun Zoom</h2><form action="/zoom/account" method="post">@csrf<input type="hidden" name="id" value="{{ $account->id }}"><label>Nama akun<input name="name" value="{{ $account->name }}" required></label><label>Email host Zoom<input name="host_email" type="email" value="{{ $account->host_email }}" required></label><label>Account ID baru <span class="small">(kosongkan jika tidak diubah)</span><input name="account_id" type="password" autocomplete="new-password"></label><label>Client ID baru<input name="client_id" type="password" autocomplete="new-password"></label><label>Client Secret baru<input name="client_secret" type="password" autocomplete="new-password"></label><label class="checkline"><input type="checkbox" name="active" value="1" @checked($account->active)> Akun aktif untuk permintaan baru</label><div class="actions"><button class="primary">Simpan</button><button type="button" data-close>Batal</button></div></form><form action="/zoom/account/{{ $account->id }}" method="post" onsubmit="return confirm('Hapus akun Zoom ini?')">@csrf @method('delete')<button class="danger">Hapus akun</button></form></dialog>
@empty<p class="empty">Belum ada akun Zoom.</p>@endforelse</div>
<dialog id="add-zoom-account"><h2>Tambah akun Zoom</h2><form action="/zoom/account" method="post">@csrf<label>Nama akun<input name="name" required></label><label>Email host Zoom<input name="host_email" type="email" required></label><label>Account ID<input name="account_id" type="password" autocomplete="new-password" required></label><label>Client ID<input name="client_id" type="password" autocomplete="new-password" required></label><label>Client Secret<input name="client_secret" type="password" autocomplete="new-password" required></label><label class="checkline"><input type="checkbox" name="active" value="1" checked> Akun aktif untuk permintaan baru</label><div class="actions"><button class="primary">Simpan akun</button><button type="button" data-close>Batal</button></div></form></dialog></section></div>
<section class="zoom-history">
  <div class="heading"><div><h2>Riwayat permintaan</h2><p>Filter berdasarkan bulan kegiatan. Gunakan kalender bulan untuk memilih periode.</p></div></div>
  <div class="zoom-history-filters">
    <form method="get" action="/zoom" class="zoom-period-form">
      <label>Bulan dan tahun<input type="month" name="period" value="{{ $period === 'all' ? now('Asia/Jakarta')->format('Y-m') : $period }}" required></label>
      <button class="primary">Tampilkan</button>
    </form>
    <div class="zoom-history-links">
      <a class="zoom-filter-link" href="/zoom?period=all">Semua riwayat</a>
      <a class="zoom-filter-link" href="/zoom/riwayat.pdf?period={{ urlencode($period) }}">Ekspor PDF {{ $period === 'all' ? 'semua periode' : $period }}</a>
    </div>
  </div>
  <p class="small">{{ $period === 'all' ? 'Semua periode' : \Carbon\CarbonImmutable::createFromFormat('!Y-m',$period,'Asia/Jakarta')->locale('id')->isoFormat('MMMM YYYY') }} · {{ $requests->total() }} permintaan</p>
  <div class="table-scroll"><table class="data-table zoom-history-table"><thead><tr><th>Kode</th><th>Tanggal rapat</th><th>Judul kegiatan</th><th>PIC / Unit</th><th>Peserta</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($requests as $item)
      <tr><td><strong>{{ $item->code ?: $item->id }}</strong></td><td class="date-cell">{{ \Carbon\CarbonImmutable::parse($item->starts_at)->format('d/m/Y H:i') }} WIB</td><td>{{ $item->agenda }}</td><td>{{ $item->requester_name }}<br><span class="small">{{ $item->unit }}</span></td><td>{{ $item->participants }}</td><td>{{ ucfirst($item->status) }}</td><td><a href="/zoom/{{ $item->id }}">Lihat / Edit</a></td></tr>
    @empty
      <tr><td colspan="7" class="table-empty">Belum ada permintaan Zoom pada periode ini.</td></tr>
    @endforelse
  </tbody></table></div>
  @include('pager',['items'=>$requests])
</section>
@endsection
