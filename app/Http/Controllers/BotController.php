<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use App\Services\MonthlyReportChat;
use App\Services\ReportText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BotController extends Controller
{
    public function receive(Request $request)
    {
        $token=config('services.report_bot.token');
        abort_unless($token && hash_equals($token,(string)$request->bearerToken()),403);
        $d=$request->validate(['phone'=>'required|regex:/^[1-9][0-9]{7,14}$/','group'=>'nullable|string|max:100',
            'mentioned'=>'required|boolean','id'=>'required|string|max:255','text'=>'present|nullable|string|max:10000','timestamp'=>'required|integer|min:1']);
        $approver=DB::table('zoom_approvers')->where('phone',$d['phone'])->where('active',true)->exists();
        $employee=DB::table('employees')->where('phone',$d['phone'])->where('active',true)->first();
        $group=$d['group']??null;
        $text=trim($d['text']??'');
        $command=preg_match('~^/(?:halo|zoom|setuju|tolak|akun|tutorial|penting|cuti|izin|sakit|pdf|laporan)(?:\s|$)~i',$text);
        if ($group && ((!$d['mentioned'] && !$command) || !DB::table('report_groups')->where('jid',$group)->exists())) {
            return response()->noContent();
        }
        if (strcasecmp($text, '/halo') === 0) {
            $menu = $employee
                ? "*Menu Kanal IT Setwapres*\n\n"
                    . "/tutorial — panduan lengkap cara memakai bot.\n"
                    . "Laporan harian — kirim uraian pekerjaan; tanggal yang tidak ditulis akan memakai hari ini.\n"
                    . "/laporan BULAN TAHUN — lihat daftar laporan bulanan Anda, misalnya /laporan November 2026.\n"
                    . "/cuti, /izin, /sakit — catat kehadiran dengan tanggal lengkap.\n"
                    . "/pdf — unduh laporan bulanan sendiri lewat chat pribadi.\n"
                    . "/akun — cari dan buka Clipboard Akun lewat chat pribadi.\n"
                    . "/penting — lihat postingan dan informasi penting.\n"
                    . "/zoom — ajukan permintaan ruang Zoom lewat chat pribadi.\n\n"
                    . "Di grup yang diizinkan, perintah berawalan / dapat dikirim tanpa mention bot."
                : "*Menu Kanal IT Setwapres*\n\n"
                    . "/zoom — ajukan permintaan ruang Zoom lewat chat pribadi.\n\n"
                    . "Kirim /zoom langsung ke chat pribadi bot untuk memulai.";
            return response()->json(['reply'=>$menu]);
        }
        $zoomCommand=preg_match('~^/(zoom|setuju|tolak|batal)(?:\s|$)~i',$text);
        $zoomDraft=!$group && \Illuminate\Support\Facades\Cache::has('zoom-draft:'.$d['phone']);
        if (!$employee && !$approver && !preg_match('~^/zoom(?:\s|$)~i',$text) && !$zoomDraft) return response()->noContent();
        if ($zoomCommand || $zoomDraft) {
            if ($group) return response()->json(['reply'=>'Permintaan dan persetujuan Zoom melalui chat pribadi bot.']);
            return response()->json(\App\Services\ZoomChat::handle($d['phone'],$text,$d['id'],$d['timestamp']));
        }
        if (!$employee) return response()->noContent();
        if (preg_match('~^/laporan(?:\s|$)~i',$text)) {
            return response()->json(['reply'=>MonthlyReportChat::forEmployee($employee,$text)]);
        }
        if (preg_match('~^/akun(?:\s+(.*))?$~isu',$text,$match)) {
            if ($group) return response()->json(['reply'=>'Kirim /akun lewat chat pribadi ke bot.']);
            $query=DB::table('office_accounts')->orderBy('label')->orderBy('id');
            if (isset($match[1])) $query->where('label','like','%'.trim($match[1]).'%');
            $rows=$query->limit(21)->get(['id','label']);
            $items=$rows->take(20)->values();
            \Illuminate\Support\Facades\Cache::put('office-menu:'.$employee->id.':'.$employee->phone,$items->pluck('id')->all(),now()->addMinutes(5));
            if ($items->isEmpty()) return response()->json(['reply'=>'Tidak ada akun yang cocok. Kirim /akun untuk melihat daftar.']);
            $lines=['*Clipboard Akun*'];
            foreach ($items as $i=>$row) $lines[]=($i+1).'. '.$row->label;
            if ($rows->count()>20) $lines[]='Ada lebih banyak akun. Cari dengan /akun diikuti nama akun.';
            $lines[]='Balas dengan nomor pilihan dalam 5 menit untuk melihat username dan password.';
            return response()->json(['reply'=>implode("\n",$lines)]);
        }
        if (!$group && ctype_digit($text)) {
            $ids=\Illuminate\Support\Facades\Cache::get('office-menu:'.$employee->id.':'.$employee->phone,[]);
            $id=$ids[(int)$text-1]??null;
            $account=$id ? DB::table('office_accounts')->find($id) : null;
            if (!$account) return response()->json(['reply'=>'Pilihan tidak tersedia. Kirim /akun untuk membuka daftar.']);
            return response()->json(['reply'=>$account->label."\nUsername: ".\Illuminate\Support\Facades\Crypt::decryptString($account->username)
                ."\nPassword: ".\Illuminate\Support\Facades\Crypt::decryptString($account->password)])->header('Cache-Control','private, no-store');
        }
        if (strcasecmp($text, '/tutorial') === 0) {
            return response()->json(['reply' => <<<'GUIDE'
*Panduan Kanal IT Setwapres*

*Laporan harian*
Kirim aktivitas lewat chat pribadi. Di grup yang diizinkan, sebut akun bot dengan fitur @mention, lalu tulis aktivitasnya. Contoh:

24 November 2026
1. Memeriksa jaringan ruang rapat.
2. Mengganti kabel proyektor.
Status: Selesai
Jenis: Permintaan
Kendala: Menunggu adaptor cadangan

Tanggal dan rincian tambahan bersifat opsional. Tanpa tanggal lengkap, bot memakai tanggal pesan dalam WIB. Kehadiran otomatis Masuk. Anda juga bisa menambahkan baris Keterangan: sesuai kebutuhan. Setelah tersimpan, bot membalas dengan tanggal dan nomor laporan.

*Absensi* — kirim salah satu sesuai kondisi:
/cuti 25 nov 2026
/izin 25 nov 2026
/sakit 25 nov 2026

*PDF pribadi* — kirim lewat chat pribadi:
/pdf untuk bulan ini
/pdf 2026-11 untuk November 2026

*Daftar laporan bulanan*
Kirim /laporan November 2026 untuk melihat laporan Anda urut tanggal. Hari kerja tanpa laporan tetap ditampilkan; Sabtu dan Minggu tampil jika ada laporan.

*Akun (chat pribadi)*
Kirim /akun atau /akun diikuti nama akun. Balas angka sesuai daftar untuk melihat username dan password.

*Postingan penting*
Kirim /penting untuk melihat daftar. Lalu kirim /penting 3 untuk membuka postingan nomor 3. Ganti angka sesuai daftar.

Kirim /halo untuk melihat menu singkat, atau /tutorial untuk melihat panduan ini lagi. Di grup, perintah berawalan / dapat dikirim tanpa mention.
GUIDE
            ]);
        }
        if (preg_match('~^/penting\b~i',$text)) {
            return PinnedPostController::forChat($text);
        }
        if (preg_match('~^/pdf\b~i',$text)) {
            if ($group) return response()->json(['reply'=>'Kirim perintah /pdf lewat chat pribadi.']);
            if (!preg_match('~^/pdf(?:\s+(\d{4}-(?:0[1-9]|1[0-2])))?$~i',$text,$m)) {
                return response()->json(['reply'=>'Gunakan /pdf YYYY-MM, misalnya /pdf 2026-10.']);
            }
            $month=$m[1]??\Carbon\CarbonImmutable::createFromTimestamp($d['timestamp'],'Asia/Jakarta')->format('Y-m');
            if (!DB::table('daily_reports')->where('employee_id',$employee->id)->where('report_date','like',$month.'-%')->exists()) {
                return response()->json(['reply'=>'Belum ada laporan Anda untuk '.$month.'.']);
            }
            return MonthlyReport::download($employee,$month);
        }
        try {
            $data=ReportText::parse($text,$d['timestamp']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['reply'=>$e->getMessage()]);
        }
        DB::table('daily_reports')->insertOrIgnore($data+['employee_id'=>$employee->id,'author'=>$employee->name,
            'message_id'=>$d['id'],'created_at'=>now(),'updated_at'=>now()]);
        $row=DB::table('daily_reports')->where('message_id',$d['id'])->where('employee_id',$employee->id)->firstOrFail();
        return response()->json(['reply'=>'Tersimpan · '.($data['attendance']??'Masuk').' · '.$employee->name.' · '.\Carbon\Carbon::parse($row->report_date)->format('d/m/Y').' · Laporan #'.$row->id]);
    }
}
