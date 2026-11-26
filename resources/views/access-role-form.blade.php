<form method="post" action="/akses/role{{ $role ? '/'.$role->id : '' }}">@csrf @if($role)@method('put')@endif
<label>Nama peran<input name="name" value="{{ $role?->name }}" maxlength="100" required></label>
<p>Menu yang dapat diakses:</p>
@foreach($menus as $key=>$label)<label class="checkline"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,$role?->permissions ?? []))>{{ $label }}</label>@endforeach
<div class="actions"><button class="primary">Simpan peran</button><button type="button" data-close>Batal</button></div></form>
