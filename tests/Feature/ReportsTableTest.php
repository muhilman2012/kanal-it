<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class ReportsTableTest extends TestCase {
 use RefreshDatabase;
 public function test_report_tables_show_newest_date_first_for_people_and_entries():void {
  $this->actingAs(User::factory()->create());
  $older=DB::table('employees')->insertGetId(['name'=>'Pegawai Lama','phone'=>'628111111111','position'=>'Prakom','active'=>true]);
  $newer=DB::table('employees')->insertGetId(['name'=>'Pegawai Baru','phone'=>'628222222222','position'=>'Prakom','active'=>true]);
  foreach([[$older,'2026-11-01','Laporan awal'],[$older,'2026-11-10','Laporan berikut'],[$newer,'2026-11-20','Laporan terbaru']] as [$id,$date,$body]){
   DB::table('daily_reports')->insert(['employee_id'=>$id,'author'=>$id===$older?'Pegawai Lama':'Pegawai Baru','report_date'=>$date,'attendance'=>'Masuk','body'=>$body,'created_at'=>now(),'updated_at'=>now()]);
  }
  $main=$this->get('/laporan')->assertOk()->assertSee('<table',false)->assertSee('Tanggal terakhir');
  $this->assertLessThan(strpos($main->getContent(),'Pegawai Lama'),strpos($main->getContent(),'Pegawai Baru'));
  $detail=$this->get('/laporan?employee='.$older)->assertOk()->assertSee('<table',false)->assertSee('Kendala');
  $this->assertLessThan(strpos($detail->getContent(),'Laporan awal'),strpos($detail->getContent(),'Laporan berikut'));
 }
}
