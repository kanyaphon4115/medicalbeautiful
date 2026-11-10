<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('treatment_requests', function (Blueprint $table) {
            $table->foreignId('selected_hospital_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('THB');
            $table->text('details');
            $table->date('valid_until');
            $table->timestamps();
            $table->unique(['treatment_request_id', 'hospital_id']);
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('treatment_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('Confirmed');
            $table->string('deposit_status', 20)->default('Pending');
            $table->date('appointment_date')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('bookings'); Schema::dropIfExists('quotations');
        Schema::table('treatment_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('selected_hospital_id'));
    }
};
