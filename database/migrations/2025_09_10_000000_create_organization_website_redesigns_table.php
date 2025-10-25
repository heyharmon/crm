<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_website_redesigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('digest', 64)->nullable();
            $table->string('wayback_timestamp', 14);
            $table->timestamp('captured_at')->nullable();
            $table->unsignedInteger('persistence_days')->default(0);
            $table->boolean('is_major')->default(true);
            $table->timestamps();
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->date('last_major_redesign_at')->nullable()->after('website');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('last_major_redesign_at');
        });

        Schema::dropIfExists('organization_website_redesigns');
    }
};
