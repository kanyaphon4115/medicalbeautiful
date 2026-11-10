<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('users',function(Blueprint $table){$table->json('notification_preferences')->nullable();$table->string('preferred_language',2)->default('en');$table->timestamp('account_deletion_requested_at')->nullable();}); }
 public function down(): void { Schema::table('users',function(Blueprint $table){$table->dropColumn(['notification_preferences','preferred_language','account_deletion_requested_at']);}); }
};
