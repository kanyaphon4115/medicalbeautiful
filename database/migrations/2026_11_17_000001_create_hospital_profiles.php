<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospital_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hospital_id')->unique()->constrained('users');
            foreach (['name', 'city', 'country', 'contact_person', 'phone', 'email', 'website', 'maps_url', 'operating_hours'] as $field) {
                $t->string($field)->nullable();
            }
            $t->string('short_description', 500)->nullable();
            $t->text('description')->nullable();
            $t->text('address')->nullable();
            $t->json('treatments')->nullable();
            $t->text('specialties')->nullable();
            $t->text('treatment_description')->nullable();
            $t->string('logo_path')->nullable();
            $t->string('main_path')->nullable();
            $t->json('gallery')->nullable();
            $t->text('registered_name')->nullable();
            $t->text('license_number')->nullable();
            $t->text('verification_documents')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('hospital_verification_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hospital_profile_id')->constrained();
            $t->text('payload');
            $t->string('status', 20)->default('pending')->index();
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->text('review_note')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospital_verification_changes');
        Schema::dropIfExists('hospital_profiles');
    }
};
