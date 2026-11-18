<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{Schema::table('patient_reviews',function(Blueprint $t){$t->foreignId('booking_id')->nullable()->change();$t->uuid('submission_key')->nullable()->unique();});}
 public function down():void{Schema::table('patient_reviews',function(Blueprint $t){$t->dropUnique(['submission_key']);$t->dropColumn('submission_key');});}
};
