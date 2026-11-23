<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('username');
            $table->text('password');
            $table->timestamps();
        });
        Schema::create('daily_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->index();
            $table->string('author');
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_reports');
        Schema::dropIfExists('office_accounts');
    }
};
