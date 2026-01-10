<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('country_phone_codes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('iso_code', 3)->unique();
            $table->string('dial_code', 8);
            $table->unsignedTinyInteger('min_nsn_length')->default(4);
            $table->unsignedTinyInteger('max_nsn_length')->default(15);
            $table->string('example_format')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
            $table->index('dial_code');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_phone_code_id')
                ->nullable()
                ->after('phone')
                ->constrained('country_phone_codes');
        });

        Schema::table('mobile_otps', function (Blueprint $table) {
            $table->foreignId('country_phone_code_id')
                ->nullable()
                ->after('user_id')
                ->constrained('country_phone_codes')
                ->nullOnDelete();
        });

        Schema::table('mobile_registration_otps', function (Blueprint $table) {
            $table->foreignId('country_phone_code_id')
                ->nullable()
                ->after('name')
                ->constrained('country_phone_codes')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_registration_otps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_phone_code_id');
        });

        Schema::table('mobile_otps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_phone_code_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('country_phone_code_id');
        });

        Schema::dropIfExists('country_phone_codes');
    }
};
