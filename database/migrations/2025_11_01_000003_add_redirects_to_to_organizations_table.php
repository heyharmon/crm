<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('redirects_to')->nullable()->after('website');
        });

        if (Schema::hasColumn('organizations', 'old_website')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('old_website');
            });
        }
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('redirects_to');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('old_website')->nullable()->after('website');
        });
    }
};
