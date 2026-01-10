<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('country_phone_codes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $uniqueExists = false;
        $indexExists = false;

        if ($driver === 'mysql') {
            $indexes = DB::select('SHOW INDEX FROM country_phone_codes WHERE Column_name = ?', ['dial_code']);

            foreach ($indexes as $index) {
                $name = $index->Key_name ?? null;

                if ($name === 'country_phone_codes_dial_code_unique') {
                    $uniqueExists = true;
                }

                if ($name === 'country_phone_codes_dial_code_index') {
                    $indexExists = true;
                }
            }
        } elseif ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('country_phone_codes')");

            foreach ($indexes as $index) {
                $name = $index->name ?? $index->Name ?? null;

                if ($name === 'country_phone_codes_dial_code_unique') {
                    $uniqueExists = true;
                }

                if ($name === 'country_phone_codes_dial_code_index') {
                    $indexExists = true;
                }
            }
        } else {
            // Unsupported driver for this adjustment; bail out quietly.
            return;
        }

        Schema::table('country_phone_codes', function (Blueprint $table) use ($uniqueExists, $indexExists) {
            if ($uniqueExists && Schema::hasColumn('country_phone_codes', 'dial_code')) {
                $table->dropUnique('country_phone_codes_dial_code_unique');
            }

            if (! $indexExists && Schema::hasColumn('country_phone_codes', 'dial_code')) {
                $table->index('dial_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('country_phone_codes')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            return;
        }

        Schema::table('country_phone_codes', function (Blueprint $table) {
            if (Schema::hasColumn('country_phone_codes', 'dial_code')) {
                $table->dropIndex(['dial_code']);
                $table->unique('dial_code');
            }
        });
    }
};
