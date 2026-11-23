<?php
use App\Http\Controllers\BotController;
use Illuminate\Support\Facades\Route;

Route::post('/laporan/masuk',[BotController::class,'receive']);