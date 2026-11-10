<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('treatment_requests', function (Blueprint $table) {
   $table->id(); $table->uuid('reference')->unique(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
   $table->string('name',80); $table->string('email',254); $table->string('procedure',80);
   $table->text('goals')->nullable(); $table->date('preferred_surgery_date')->nullable(); $table->longText('medical_information')->nullable();
   $table->string('status',30)->default('pending'); $table->timestamp('consented_at'); $table->text('consent_text'); $table->timestamps();
  });
  Schema::create('request_photos', function (Blueprint $table) {
   $table->id(); $table->foreignId('treatment_request_id')->constrained()->cascadeOnDelete();
   $table->string('path'); $table->string('original_name'); $table->string('mime_type',80); $table->unsignedBigInteger('size'); $table->timestamps();
  });
 }
 public function down(): void { Schema::dropIfExists('request_photos'); Schema::dropIfExists('treatment_requests'); }
};
