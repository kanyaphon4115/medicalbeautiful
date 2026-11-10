<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->unique(['reader_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
    }
};
