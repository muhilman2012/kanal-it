@extends('layout')
@section('title','Akses pengguna')
@section('content')
<h1>Akses pengguna</h1>
<p>Atur menu yang dapat dibuka setiap peran, lalu kaitkan akun login dengan pegawai yang sudah terdaftar untuk laporan harian.</p>
<section class="heading"><h2>Peran dan izin</h2><button class="primary" data-open="role-add">Tambah peran</button></section>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Peran</th><th>Menu yang diizinkan</th><th>Pengguna</th><th>Aksi</th></tr></thead><tbody>
@foreach($roles as $role)<tr><td><strong>{{ $role->name }}</strong>@if($role->is_admin)<div class="small">Akses penuh · peran tetap</div>@endif</td><td>{{ $role->is_admin ? 'Semua menu' : (collect($role->permissions)->map(fn($p) => $menus[$p] ?? $p)->join(', ') ?: 'Hanya beranda dan pengaturan akun') }}</td><td>{{ $users->where('access_role_id',$role->id)->count() }}</td><td>@unless($role->is_admin)<button data-open="role-{{ $role->id }}">Edit</button> <form class="inline-form" method="post" action="/akses/role/{{ $role->id }}" onsubmit="return confirm('Hapus peran ini?')">@csrf @method('delete')<button class="danger">Hapus</button></form>@endunless</td></tr>
@endforeach
</tbody></table></div>
@foreach($roles->where('is_admin',false) as $role)
<dialog id="role-{{ $role->id }}"><h2>Edit peran</h2>@include('access-role-form',['role'=>$role])</dialog>
@endforeach
<dialog id="role-add"><h2>Tambah peran</h2>@include('access-role-form',['role'=>null])</dialog>

<section class="heading" style="margin-top:32px"><h2>Akun login</h2><button class="primary" data-open="user-add">Tambah akun</button></section>
<p class="small">Pegawai yang belum mempunyai akun login tetap dapat mengirim laporan lewat WhatsApp. Kata sandi baru hanya ditampilkan saat Anda mengisinya dan dapat diganti sendiri setelah login.</p>
<div class="table-scroll"><table class="data-table"><thead><tr><th>Nama dan email</th><th>Pegawai laporan</th><th>Peran</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
@foreach($users as $member)<tr><td><strong>{{ $member->name }}</strong><div class="small">{{ $member->email }}</div></td><td>{{ $employees->firstWhere('id',$member->employee_id)?->name ?? 'Belum dikaitkan' }}</td><td>{{ $member->accessRole?->name ?? 'Belum ada peran' }}</td><td>{{ $member->active ? 'Aktif' : 'Nonaktif' }}</td><td><button data-open="user-{{ $member->id }}">Edit</button>@if($member->id !== auth()->id()) <form class="inline-form" method="post" action="/akses/pengguna/{{ $member->id }}" onsubmit="return confirm('Hapus akun login ini? Data laporan tetap tersimpan.')">@csrf @method('delete')<button class="danger">Hapus</button></form>@endif</td></tr>
@endforeach
</tbody></table></div>
@foreach($users as $member)<dialog id="user-{{ $member->id }}"><h2>Edit akun login</h2>@include('access-user-form',['member'=>$member])</dialog>@endforeach
<dialog id="user-add"><h2>Tambah akun login</h2>@include('access-user-form',['member'=>null])</dialog>
@endsection
