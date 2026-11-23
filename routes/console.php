<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

\Illuminate\Support\Facades\Artisan::command('kanal:admin {email}', function () {
    $email=$this->argument('email');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || \App\Models\User::whereEmail($email)->exists()) {
        $this->error('Email tidak valid atau sudah terdaftar.'); return 1;
    }
    $password=$this->secret('Password baru (minimal 12 karakter)');
    if (strlen((string)$password)<12 || $password!==$this->secret('Ulangi password')) return 1;
    \App\Models\User::create(['name'=>'Admin IT','email'=>$email,'password'=>$password]);
    $this->info('Admin berhasil dibuat.');
});
