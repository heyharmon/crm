<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('website_redesign_status')->nullable()->after('last_major_redesign_at');
            $table->text('website_redesign_status_message')->nullable()->after('website_redesign_status');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'website_redesign_status',
                'website_redesign_status_message',
            ]);
        });
    }
};
