<?php

use App\Http\Controllers\PortalController as Portal;
use App\Http\Controllers\ReportAdminController as ReportAdmin;
use App\Http\Controllers\PinnedPostController as Pins;
use App\Http\Controllers\ZoomController as Zoom;
use App\Http\Controllers\AccessController as Access;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [Portal::class, 'login'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'auth.session', 'menu:portal', 'cache.headers:no_store;private'])->group(function () {
    Route::get('/akses', [Access::class, 'index'])->middleware('menu:access');
    Route::post('/akses/role', [Access::class, 'saveRole'])->middleware('menu:access');
    Route::put('/akses/role/{id}', [Access::class, 'saveRole'])->middleware('menu:access')->whereNumber('id');
    Route::delete('/akses/role/{id}', [Access::class, 'deleteRole'])->middleware('menu:access')->whereNumber('id');
    Route::post('/akses/pengguna', [Access::class, 'saveUser'])->middleware('menu:access');
    Route::put('/akses/pengguna/{id}', [Access::class, 'saveUser'])->middleware('menu:access')->whereNumber('id');
    Route::delete('/akses/pengguna/{id}', [Access::class, 'deleteUser'])->middleware('menu:access')->whereNumber('id');
    Route::middleware('menu:zoom')->group(function () {
    Route::get('/zoom',[Zoom::class,'index']);
    Route::get('/zoom/{id}',[Zoom::class,'show'])->whereNumber('id');
    Route::delete('/zoom/{id}',[Zoom::class,'deleteRequest'])->whereNumber('id');
    Route::post('/zoom/approver',[Zoom::class,'approver']);
    Route::put('/zoom/approver/{id}',[Zoom::class,'approver'])->whereNumber('id');
    Route::delete('/zoom/approver/{id}',[Zoom::class,'deleteApprover'])->whereNumber('id');
    Route::post('/zoom/account',[Zoom::class,'account']);
    Route::delete('/zoom/account/{id}',[Zoom::class,'deleteAccount'])->whereNumber('id');
    });
    Route::get('/', [Portal::class, 'home'])->name('dashboard');
    Route::post('/keluar', [Portal::class, 'logout'])->withoutMiddleware('menu:portal');
    Route::view('/pengaturan', 'settings');
    Route::put('/pengaturan', [Portal::class, 'changePassword']);
    Route::middleware('menu:accounts')->group(function () {
    Route::get('/akun', [Portal::class, 'accounts']);
    Route::post('/akun', [Portal::class, 'saveAccount']);
    Route::put('/akun/{id}', [Portal::class, 'saveAccount'])->whereNumber('id');
    Route::post('/akun/{id}/salin', [Portal::class, 'copyPassword'])->whereNumber('id');
    Route::delete('/akun/{id}', [Portal::class, 'deleteAccount'])->whereNumber('id');
    });
    Route::middleware('menu:pins')->group(function () {
    Route::get('/penting',[Pins::class,'index']);
    Route::post('/penting/{id?}',[Pins::class,'save'])->whereNumber('id');
    Route::get('/penting/{id}/file',[Pins::class,'download'])->whereNumber('id');
    Route::delete('/penting/{id}',[Pins::class,'delete'])->whereNumber('id');
    });
    Route::middleware('menu:report_bot')->group(function () {
    Route::get('/laporan/bot',[ReportAdmin::class,'index']);
    Route::get('/laporan/bot/qr',[ReportAdmin::class,'qr']);
    Route::post('/laporan/bot/lepas',[ReportAdmin::class,'disconnect']);
    Route::post('/laporan/bot/pegawai/{id?}',[ReportAdmin::class,'employee'])->whereNumber('id');
    Route::post('/laporan/bot/grup',[ReportAdmin::class,'group']);
    Route::post('/laporan/{id}/pegawai',[ReportAdmin::class,'assign'])->whereNumber('id');
    });
    Route::middleware('menu:reports')->group(function () {
    Route::get('/laporan/bulanan',[ReportAdmin::class,'pdf']);
    Route::get('/laporan', [Portal::class, 'reports']);
    Route::put('/laporan/{id}', [Portal::class, 'saveReport'])->whereNumber('id');
    Route::delete('/laporan/{id}', [Portal::class, 'deleteReport'])->whereNumber('id');
    Route::post('/laporan', [Portal::class, 'saveReport']);
    });
});
