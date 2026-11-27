<?php

namespace App\Http\Controllers;

use App\Models\AccessRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccessController extends Controller
{
    public const MENUS = [
        'accounts' => 'Clipboard Akun',
        'reports' => 'Laporan Harian',
        'report_bot' => 'Pengaturan Bot dan Pegawai',
        'zoom' => 'Permintaan Zoom',
        'pins' => 'Postingan Penting',
        'bot_guide' => 'Panduan Bot WhatsApp',
        'lobby' => 'Layar Lobby',
        'announcer' => 'Pengumuman Kantor',
    ];

    public function index()
    {
        return view('access', [
            'roles' => AccessRole::orderByDesc('is_admin')->orderBy('name')->get(),
            'users' => User::with('accessRole')->orderBy('name')->get(),
            'employees' => DB::table('employees')->orderBy('name')->get(),
            'menus' => self::MENUS,
        ]);
    }

    public function saveRole(Request $request, ?int $id = null)
    {
        $role = $id ? AccessRole::findOrFail($id) : new AccessRole();
        abort_if($role->is_admin, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('access_roles')->ignore($id)],
            'permissions' => 'array',
            'permissions.*' => ['string', Rule::in(array_keys(self::MENUS))],
        ]);
        $role->fill([
            'name' => $data['name'],
            'permissions' => array_values(array_unique($data['permissions'] ?? [])),
            'is_admin' => false,
        ])->save();
        return redirect('/akses')->with('status', 'Peran tersimpan.');
    }

    public function deleteRole(int $id)
    {
        $role = AccessRole::findOrFail($id);
        abort_if($role->is_admin, 403);
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'Pindahkan pengguna ke peran lain sebelum menghapus peran ini.']);
        }
        $role->delete();
        return back()->with('status', 'Peran dihapus.');
    }

    public function saveUser(Request $request, ?int $id = null)
    {
        $user = $id ? User::findOrFail($id) : new User();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($id)],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id', Rule::unique('users')->ignore($id)],
            'access_role_id' => 'required|integer|exists:access_roles,id',
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:72', 'confirmed'],
        ]);
        $role = AccessRole::findOrFail($data['access_role_id']);
        if ($id === $request->user()->id && (!$role->is_admin || !$request->boolean('active'))) {
            throw ValidationException::withMessages(['access_role_id' => 'Akun admin yang sedang digunakan harus tetap aktif dan menjadi admin.']);
        }
        if ($user->exists && $user->accessRole?->is_admin && (!$role->is_admin || !$request->boolean('active'))) {
            $this->protectLastAdmin($user);
        }
        $values = [
            'name' => $data['name'], 'email' => $data['email'],
            'employee_id' => $data['employee_id'] ?? null,
            'access_role_id' => $role->id,
            'active' => $request->boolean('active'),
        ];
        if (!empty($data['password'])) $values['password'] = Hash::make($data['password']);
        $user->fill($values)->save();
        return redirect('/akses')->with('status', 'Akun login tersimpan.');
    }

    public function deleteUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        abort_if($user->id === $request->user()->id, 403);
        if ($user->accessRole?->is_admin) $this->protectLastAdmin($user);
        $user->delete();
        return back()->with('status', 'Akun login dihapus. Data laporan pegawai tetap ada.');
    }

    private function protectLastAdmin(User $user): void
    {
        $others = User::where('id', '!=', $user->id)->where('active', true)
            ->whereHas('accessRole', fn ($q) => $q->where('is_admin', true))->exists();
        if (!$others) {
            throw ValidationException::withMessages(['access_role_id' => 'Minimal satu admin aktif harus tersedia.']);
        }
    }
}
