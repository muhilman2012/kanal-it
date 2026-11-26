<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::create('zoom_approvers',function(Blueprint $t){$t->id();$t->string('name');$t->string('phone')->unique();$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('zoom_accounts',function(Blueprint $t){$t->id();$t->string('name');$t->string('host_email')->nullable();$t->text('account_id')->nullable();$t->text('client_id')->nullable();$t->text('client_secret')->nullable();$t->boolean('active')->default(true);$t->timestamps();});
  Schema::create('zoom_requests',function(Blueprint $t){$t->id();$t->string('phone');$t->string('requester_name');$t->string('email')->nullable();$t->string('unit');$t->string('agenda');$t->dateTime('starts_at');$t->dateTime('ends_at');$t->unsignedInteger('participants');$t->string('cohost')->nullable();$t->string('recording')->default('Tidak');$t->text('notes')->nullable();$t->foreignId('approver_id')->nullable()->constrained('zoom_approvers');$t->foreignId('zoom_account_id')->nullable()->constrained('zoom_accounts');$t->string('status')->default('pending');$t->string('source_message_id')->nullable()->unique();$t->string('zoom_meeting_id')->nullable();$t->text('join_url')->nullable();$t->text('start_url')->nullable();$t->text('rejection_reason')->nullable();$t->timestamps();});
  Schema::create('zoom_request_logs',function(Blueprint $t){$t->id();$t->foreignId('zoom_request_id')->constrained()->cascadeOnDelete();$t->string('event');$t->string('actor')->nullable();$t->text('detail')->nullable();$t->timestamps();});
 }
 public function down():void {Schema::dropIfExists('zoom_request_logs');Schema::dropIfExists('zoom_requests');Schema::dropIfExists('zoom_accounts');Schema::dropIfExists('zoom_approvers');}
};