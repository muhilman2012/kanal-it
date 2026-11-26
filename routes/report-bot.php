<?php
use App\Http\Controllers\BotController;
use Illuminate\Support\Facades\Route;

Route::post('/laporan/masuk',[BotController::class,'receive']);
Route::get('/zoom/outbox',function(\Illuminate\Http\Request $request){
 $token=config('services.report_bot.token');
 abort_unless($token && hash_equals($token,(string)$request->bearerToken()),403);
 return response()->json(\Illuminate\Support\Facades\DB::table('zoom_outbox')->whereNull('sent_at')->where(function($q){$q->whereNull('available_at')->orWhere('available_at','<=',now());})->where(function($q){$q->where('pending_only',false)->orWhereIn('zoom_request_id',\Illuminate\Support\Facades\DB::table('zoom_requests')->select('id')->where('status','pending'));})->orderBy('id')->limit(20)->get());
});
Route::post('/zoom/outbox/{id}/sent',function(\Illuminate\Http\Request $request,int $id){
 $token=config('services.report_bot.token');
 abort_unless($token && hash_equals($token,(string)$request->bearerToken()),403);
 \Illuminate\Support\Facades\DB::table('zoom_outbox')->where('id',$id)->whereNull('sent_at')->update(['sent_at'=>now(),'updated_at'=>now()]);
 return response()->noContent();
})->whereNumber('id');
