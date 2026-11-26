<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void {Schema::create('zoom_outbox',function(Blueprint $t){$t->id();$t->string('phone');$t->text('body');$t->timestamp('sent_at')->nullable();$t->timestamps();});}public function down():void {Schema::dropIfExists('zoom_outbox');}};