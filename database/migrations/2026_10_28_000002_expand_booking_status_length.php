<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 64)->default('Confirmed')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->whereRaw('LENGTH(status) > 20')->exists()) {
            throw new RuntimeException('Cannot shorten booking status while longer status values exist.');
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('status', 20)->default('Confirmed')->change();
        });
    }
};
