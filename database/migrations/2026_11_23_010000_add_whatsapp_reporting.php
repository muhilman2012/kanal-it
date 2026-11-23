<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('position');
            $t->string('phone')->unique();
            $t->boolean('active')->default(true);
        });
        Schema::create('report_groups', function (Blueprint $t) {
            $t->string('jid')->primary();
        });
        Schema::table('daily_reports', function (Blueprint $t) {
            $t->foreignId('employee_id')->nullable()->constrained();
            $t->string('message_id')->nullable()->unique();
            foreach (['attendance','progress','obstacle','initiative','note'] as $field) {
                $t->text($field)->nullable();
            }
            $t->index(['employee_id','report_date']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_reports', function (Blueprint $t) {
            $t->dropIndex(['employee_id','report_date']);
            $t->dropUnique(['message_id']);
            $t->dropForeign(['employee_id']);
            $t->dropColumn(['employee_id','message_id','attendance','progress','obstacle','initiative','note']);
        });
        Schema::dropIfExists('report_groups');
        Schema::dropIfExists('employees');
    }
};