<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $credentials['active'] = true;
        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Email atau password belum sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $intended = $request->session()->pull('url.intended', '/');
        $target = parse_url($intended);
        $configured = parse_url(config('app.url'));
        $sameHost = !isset($target['host']) || (
            strcasecmp($target['host'], $configured['host'] ?? '') === 0
            && (!isset($target['port']) || $target['port'] === ($configured['port'] ?? null))
        );
        $path = $sameHost ? ($target['path'] ?? '/') : '/';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) $path = '/';
        if ($sameHost && isset($target['query'])) $path .= '?'.$target['query'];
        return redirect()->away(rtrim(config('app.url'), '/').$path);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => 'required|string|min:12|max:72|confirmed'], [
            'current_password.current_password' => 'Password saat ini belum sesuai.',
            'password.min' => 'Gunakan minimal 12 karakter.',
            'password.confirmed' => 'Konfirmasi password belum sama.',
        ]);
        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();
        return back()->with('status', 'Password kanal berhasil diganti.');
    }

    public function home()
    {
        $user = request()->user();
        return view('home', [
            'accounts' => $user->hasMenuPermission('accounts') ? DB::table('office_accounts')->count() : 0,
            'reports' => $user->hasMenuPermission('reports') ? DB::table('daily_reports')->count() : 0,
        ]);
    }

    public function accounts(Request $request)
    {
        $q = $request->validate(['q' => 'nullable|string|max:150'])['q'] ?? '';
        $accounts = DB::table('office_accounts')->select('id', 'label', 'username')->where('label', 'like', '%'.$q.'%')->orderBy('label')->paginate(20)->withQueryString();
        foreach ($accounts as $account) {
            $account->username = Crypt::decryptString($account->username);
        }
        return view('accounts', compact('accounts', 'q'));
    }

    public function saveAccount(Request $request, ?int $id = null)
    {
        $data = $request->validate(['label' => 'required|string|max:150', 'username' => 'required|string|max:255', 'password' => [$id ? 'nullable' : 'required', 'string', 'max:1024']]);
        $values = ['label' => $data['label'], 'username' => Crypt::encryptString($data['username']), 'updated_at' => now()];
        if ($request->filled('password')) {
            $values['password'] = Crypt::encryptString($data['password']);
        }
        if ($id) {
            abort_unless(DB::table('office_accounts')->where('id', $id)->update($values), 404);
        } else {
            DB::table('office_accounts')->insert($values + ['created_at' => now()]);
        }
        return redirect('/akun')->with('status', 'Akun tersimpan.');
    }

    public function copyPassword(int $id)
    {
        $account = DB::table('office_accounts')->find($id);
        abort_unless($account, 404);
        return response()->json(['value' => Crypt::decryptString($account->password)])->header('Cache-Control', 'private, no-store');
    }

    public function deleteAccount(int $id)
    {
        abort_unless(DB::table('office_accounts')->where('id', $id)->delete(), 404);
        return redirect('/akun')->with('status', 'Akun dihapus.');
    }

    public function reports(Request $request)
    {
        $filters=$request->validate(['month'=>'nullable|date_format:Y-m','employee'=>'nullable|integer|exists:employees,id','author'=>'nullable|string|max:100']);
        $month=$filters['month']??null;
        $employee=isset($filters['employee']) ? DB::table('employees')->find($filters['employee']) : null;
        $author=$employee->name??($filters['author']??null);
        $employees=DB::table('employees')->orderBy('name')->get();
        $query=DB::table('daily_reports');
        if ($month) {
            $start=\Carbon\CarbonImmutable::parse($month.'-01');
            $query->whereBetween('report_date',[$start->toDateString(),$start->endOfMonth()->toDateString()]);
        }
        if ($author !== null) {
            if ($employee) $query->where('employee_id',$employee->id);
            else $query->whereNull('employee_id')->where('author',$author);
            $reports=$query->orderByDesc('report_date')->orderByDesc('id')->paginate(20)->withQueryString();
            return view('report-person',compact('employee','author','reports','month','employees'));
        }
        $employeeCounts=(clone $query)->whereNotNull('employee_id')->groupBy('employee_id')
            ->selectRaw('employee_id,count(*) as total,max(report_date) as last_date')->get()->keyBy('employee_id');
        $manual=$query->whereNull('employee_id')->groupBy('author')
            ->selectRaw('author,count(*) as total,max(report_date) as last_date')->get();
        $people=$employees->map(function($person) use($employeeCounts){
            $summary=$employeeCounts->get($person->id);
            return (object)['name'=>$person->name,'position'=>$person->position,'employee_id'=>$person->id,
                'total'=>$summary->total??0,'last_date'=>$summary->last_date??null];
        })->concat($manual->map(fn($person)=>(object)[
            'name'=>$person->author,'position'=>'Pelapor manual','employee_id'=>null,
            'total'=>$person->total,'last_date'=>$person->last_date,
        ]))->sort(function($a,$b){
            return strcmp($b->last_date??'',$a->last_date??'') ?: strcasecmp($a->name,$b->name);
        })->values();
        return view('reports',compact('employees','people','month'));
    }

    public function saveReport(Request $request, ?int $id=null)
    {
        $data = $request->validate(['report_date' => 'required|date_format:Y-m-d', 'employee_id'=>'nullable|integer|exists:employees,id', 'author' => 'required_without:employee_id|nullable|string|max:100', 'attendance'=>'nullable|string|max:100','progress'=>'nullable|string|max:100','obstacle'=>'nullable|string|max:1000','initiative'=>'nullable|string|max:100','note'=>'nullable|string|max:1000', 'body' => 'required_unless:attendance,Cuti,Izin,Sakit|nullable|string|max:10000']);
        $data['attendance'] ??= 'Masuk';
        if (!empty($data['employee_id'])) {
            $data['author']=DB::table('employees')->where('id',$data['employee_id'])->value('name');
        }
        $data['body'] ??= '';
        if ($id !== null) {
            DB::table('daily_reports')->where('id',$id)->firstOrFail();
            DB::table('daily_reports')->where('id',$id)->update($data+['updated_at'=>now()]);
        } else {
            DB::table('daily_reports')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        }
        if ($request->boolean('detail')) {
            $params=empty($data['employee_id']) ? ['author'=>$data['author']] : ['employee'=>$data['employee_id']];
            if ($request->filled('month')) $params['month']=substr($data['report_date'],0,7);
            return redirect('/laporan?'.http_build_query($params))->with('status','Laporan harian tersimpan.');
        }
        return redirect('/laporan')->with('status', 'Laporan harian tersimpan.');
    }
    public function deleteReport(Request $request, int $id)
    {
        abort_unless(DB::table('daily_reports')->where('id',$id)->delete(),404);
        return back()->with('status','Laporan dihapus.');
    }
}
