<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('treatment_requests', function (Blueprint $table) {
            $table->json('procedures')->nullable();
            $table->text('line_id')->nullable();
            $table->text('phone_number')->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('gender',30)->nullable();
            $table->unsignedTinyInteger('preferred_surgery_month')->nullable();
            $table->unsignedSmallInteger('preferred_surgery_year')->nullable();
        });
    }
    public function down(): void {
        Schema::table('treatment_requests', fn(Blueprint $table)=>$table->dropColumn(['procedures','line_id','phone_number','age','gender','preferred_surgery_month','preferred_surgery_year']));
    }
};
