<?php

use App\Http\Controllers\PortalController as Portal;
use App\Http\Controllers\ReportAdminController as ReportAdmin;
use App\Http\Controllers\PinnedPostController as Pins;
use App\Http\Controllers\ZoomController as Zoom;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [Portal::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'auth.session', 'cache.headers:no_store;private'])->group(function () {
    Route::get('/zoom',[Zoom::class,'index']);
    Route::get('/zoom/{id}',[Zoom::class,'show'])->whereNumber('id');
    Route::delete('/zoom/{id}',[Zoom::class,'deleteRequest'])->whereNumber('id');
    Route::post('/zoom/approver',[Zoom::class,'approver']);
    Route::put('/zoom/approver/{id}',[Zoom::class,'approver'])->whereNumber('id');
    Route::delete('/zoom/approver/{id}',[Zoom::class,'deleteApprover'])->whereNumber('id');
    Route::post('/zoom/account',[Zoom::class,'account']);
    Route::delete('/zoom/account/{id}',[Zoom::class,'deleteAccount'])->whereNumber('id');
    Route::get('/', [Portal::class, 'home'])->name('dashboard');
    Route::post('/keluar', [Portal::class, 'logout']);
    Route::view('/pengaturan', 'settings');
    Route::put('/pengaturan', [Portal::class, 'changePassword']);
    Route::get('/akun', [Portal::class, 'accounts']);
    Route::post('/akun', [Portal::class, 'saveAccount']);
    Route::put('/akun/{id}', [Portal::class, 'saveAccount'])->whereNumber('id');
    Route::post('/akun/{id}/salin', [Portal::class, 'copyPassword'])->whereNumber('id');
    Route::delete('/akun/{id}', [Portal::class, 'deleteAccount'])->whereNumber('id');
    Route::get('/penting',[Pins::class,'index']);
    Route::post('/penting/{id?}',[Pins::class,'save'])->whereNumber('id');
    Route::get('/penting/{id}/file',[Pins::class,'download'])->whereNumber('id');
    Route::delete('/penting/{id}',[Pins::class,'delete'])->whereNumber('id');
    Route::get('/laporan/bot',[ReportAdmin::class,'index']);
    Route::get('/laporan/bot/qr',[ReportAdmin::class,'qr']);
    Route::post('/laporan/bot/lepas',[ReportAdmin::class,'disconnect']);
    Route::post('/laporan/bot/pegawai/{id?}',[ReportAdmin::class,'employee'])->whereNumber('id');
    Route::post('/laporan/bot/grup',[ReportAdmin::class,'group']);
    Route::post('/laporan/{id}/pegawai',[ReportAdmin::class,'assign'])->whereNumber('id');
    Route::get('/laporan/bulanan',[ReportAdmin::class,'pdf']);
    Route::get('/laporan', [Portal::class, 'reports']);
    Route::put('/laporan/{id}', [Portal::class, 'saveReport'])->whereNumber('id');
    Route::delete('/laporan/{id}', [Portal::class, 'deleteReport'])->whereNumber('id');
    Route::post('/laporan', [Portal::class, 'saveReport']);
});
