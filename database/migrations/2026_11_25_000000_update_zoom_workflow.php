<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('zoom_approvers',fn(Blueprint $t)=>$t->boolean('priority')->default(false));
  Schema::table('zoom_requests',function(Blueprint $t){$t->string('code')->nullable()->unique();$t->foreignId('priority_approver_id')->nullable()->constrained('zoom_approvers')->nullOnDelete();});
  Schema::table('zoom_outbox',function(Blueprint $t){$t->foreignId('zoom_request_id')->nullable()->constrained()->cascadeOnDelete();$t->timestamp('available_at')->nullable();$t->boolean('pending_only')->default(false);});
 }
 public function down():void {
  Schema::table('zoom_outbox',function(Blueprint $t){$t->dropConstrainedForeignId('zoom_request_id');$t->dropColumn(['available_at','pending_only']);});
  Schema::table('zoom_requests',function(Blueprint $t){$t->dropConstrainedForeignId('priority_approver_id');$t->dropColumn('code');});
  Schema::table('zoom_approvers',fn(Blueprint $t)=>$t->dropColumn('priority'));
 }
};