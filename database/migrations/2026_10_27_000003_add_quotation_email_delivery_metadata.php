<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('quotations', function (Blueprint $table) {
            $table->string('last_emailed_fingerprint', 64)->nullable();
            $table->timestamp('customer_emailed_at')->nullable();
        });
    }

    public function down(): void {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['last_emailed_fingerprint', 'customer_emailed_at']));
    }
};
