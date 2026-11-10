<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->text('line_id')->nullable();
            $table->text('date_of_birth')->nullable();
            $table->text('gender')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['line_id', 'date_of_birth', 'gender']));
    }
};
