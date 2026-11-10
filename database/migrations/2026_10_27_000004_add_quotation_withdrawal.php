<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('quotations', fn (Blueprint $table) => $table->timestamp('withdrawn_at')->nullable());
    }
    public function down(): void {
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('withdrawn_at'));
    }
};
