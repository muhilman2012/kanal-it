<form method="post" action="/akses/pengguna{{ $member ? '/'.$member->id : '' }}">@csrf @if($member)@method('put')@endif
<label>Nama pengguna<input name="name" value="{{ $member?->name }}" maxlength="100" required></label>
<label>Email login<input type="email" name="email" value="{{ $member?->email }}" required></label>
<label>Pegawai laporan harian<select name="employee_id"><option value="">Tidak dikaitkan</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($member?->employee_id === $employee->id)>{{ $employee->name }} · {{ $employee->position }}</option>@endforeach</select></label>
<label>Peran<select name="access_role_id" required>@foreach($roles as $role)<option value="{{ $role->id }}" @selected($member?->access_role_id === $role->id || (!$member && $role->name === 'Pegawai'))>{{ $role->name }}</option>@endforeach</select></label>
<label>Password {{ $member ? 'baru (kosongkan jika tidak diubah)' : '' }}<input type="password" name="password" minlength="12" {{ $member ? '' : 'required' }} autocomplete="new-password"></label>
<label>Konfirmasi password<input type="password" name="password_confirmation" autocomplete="new-password"></label>
<label class="checkline"><input type="checkbox" name="active" value="1" @checked(!$member || $member->active)> Akun aktif</label>
<div class="actions"><button class="primary">Simpan akun</button><button type="button" data-close>Batal</button></div></form>
