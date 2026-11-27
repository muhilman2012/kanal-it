<?php

use App\Http\Controllers\PortalController as Portal;
use App\Http\Controllers\ReportAdminController as ReportAdmin;
use App\Http\Controllers\PinnedPostController as Pins;
use App\Http\Controllers\ZoomController as Zoom;
use App\Http\Controllers\AccessController as Access;
use App\Http\Controllers\LobbyController as Lobby;
use App\Http\Controllers\LobbyPlayerController as LobbyPlayer;
use App\Http\Controllers\AnnouncerController as Announcer;
use App\Http\Controllers\AnnouncerPlayerController as Speaker;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [Portal::class, 'login'])->middleware('throttle:5,1');
});

Route::get('/layar-lobby/{token}', [LobbyPlayer::class, 'show'])->where('token', '[a-f0-9]{48}');
Route::get('/layar-lobby/{token}/playlist', [LobbyPlayer::class, 'playlist'])->where('token', '[a-f0-9]{48}');
Route::get('/layar-lobby/{token}/media/{item}', [LobbyPlayer::class, 'media'])->where('token', '[a-f0-9]{48}')->whereNumber('item');
Route::get('/tv/{token}', [LobbyPlayer::class, 'show'])->where('token', '[A-Za-z2-9]{8}');
Route::get('/tv/{token}/playlist', [LobbyPlayer::class, 'playlist'])->where('token', '[A-Za-z2-9]{8}');
Route::get('/tv/{token}/media/{item}', [LobbyPlayer::class, 'media'])->where('token', '[A-Za-z2-9]{8}')->whereNumber('item');
Route::get('/speaker/{code}', [Speaker::class, 'show'])->where('code', '[A-Fa-f0-9]{12}');
Route::get('/speaker/{code}/audio/{schedule}', [Speaker::class, 'audio'])->where('code', '[A-Fa-f0-9]{12}')->whereNumber('schedule');

Route::middleware(['auth', 'auth.session', 'menu:portal', 'cache.headers:no_store;private'])->group(function () {
    Route::middleware('menu:announcer')->group(function () {
        Route::get('/pengumuman', [Announcer::class, 'index']);
        Route::put('/pengumuman/pengaturan', [Announcer::class, 'settings']);
        Route::post('/pengumuman/ganti-kode', [Announcer::class, 'rotate']);
        Route::post('/pengumuman/jadwal', [Announcer::class, 'saveSchedule']);
        Route::put('/pengumuman/jadwal/{id}', [Announcer::class, 'saveSchedule'])->whereNumber('id');
        Route::delete('/pengumuman/jadwal/{id}', [Announcer::class, 'deleteSchedule'])->whereNumber('id');
        Route::post('/pengumuman/blast', [Announcer::class, 'blast']);
        Route::post('/pengumuman/blast/{id}/batal', [Announcer::class, 'cancelBlast'])->whereNumber('id');
        Route::post('/pengumuman/blast/{id}/ulang', [Announcer::class, 'retryBlast'])->whereNumber('id');
    });
    Route::middleware('menu:lobby')->group(function () {
        Route::get('/lobby', [Lobby::class, 'index']);
        Route::post('/lobby/screens', [Lobby::class, 'saveScreen']);
        Route::put('/lobby/screens/{screen}', [Lobby::class, 'saveScreen']);
        Route::delete('/lobby/screens/{screen}', [Lobby::class, 'deleteScreen']);
        Route::post('/lobby/screens/{screen}/rotate', [Lobby::class, 'rotateToken']);
        Route::post('/lobby/screens/{screen}/items', [Lobby::class, 'saveItem']);
        Route::put('/lobby/screens/{screen}/items/{item}', [Lobby::class, 'saveItem']);
        Route::delete('/lobby/screens/{screen}/items/{item}', [Lobby::class, 'deleteItem']);
        Route::post('/lobby/screens/{screen}/items/{item}/move', [Lobby::class, 'moveItem']);
    });
    Route::get('/akses', [Access::class, 'index'])->middleware('menu:access');
    Route::post('/akses/role', [Access::class, 'saveRole'])->middleware('menu:access');
    Route::put('/akses/role/{id}', [Access::class, 'saveRole'])->middleware('menu:access')->whereNumber('id');
    Route::delete('/akses/role/{id}', [Access::class, 'deleteRole'])->middleware('menu:access')->whereNumber('id');
    Route::post('/akses/pengguna', [Access::class, 'saveUser'])->middleware('menu:access');
    Route::put('/akses/pengguna/{id}', [Access::class, 'saveUser'])->middleware('menu:access')->whereNumber('id');
    Route::delete('/akses/pengguna/{id}', [Access::class, 'deleteUser'])->middleware('menu:access')->whereNumber('id');
    Route::middleware('menu:zoom')->group(function () {
    Route::get('/zoom',[Zoom::class,'index']);
    Route::get('/zoom/riwayat.pdf',[Zoom::class,'historyPdf']);
    Route::get('/zoom/{id}',[Zoom::class,'show'])->whereNumber('id');
    Route::put('/zoom/{id}',[Zoom::class,'updateRequest'])->whereNumber('id');
    Route::delete('/zoom/{id}',[Zoom::class,'deleteRequest'])->whereNumber('id');
    Route::post('/zoom/approver',[Zoom::class,'approver']);
    Route::put('/zoom/approver/{id}',[Zoom::class,'approver'])->whereNumber('id');
    Route::delete('/zoom/approver/{id}',[Zoom::class,'deleteApprover'])->whereNumber('id');
    Route::post('/zoom/account',[Zoom::class,'account']);
    Route::delete('/zoom/account/{id}',[Zoom::class,'deleteAccount'])->whereNumber('id');
    });
    Route::get('/', [Portal::class, 'home'])->name('dashboard');
    Route::view('/panduan-bot', 'bot-guide')->middleware('menu:bot_guide');
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
