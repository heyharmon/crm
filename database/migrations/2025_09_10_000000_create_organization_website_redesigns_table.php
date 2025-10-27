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
            $table->string('before_wayback_timestamp', 14);
            $table->timestamp('before_captured_at')->nullable();
            $table->string('after_wayback_timestamp', 14);
            $table->timestamp('after_captured_at')->nullable();
            $table->decimal('nav_similarity', 5, 4)->nullable();
            $table->unsignedSmallInteger('before_nav_class_count')->nullable();
            $table->unsignedSmallInteger('after_nav_class_count')->nullable();
            $table->json('before_nav_classes')->nullable();
            $table->json('after_nav_classes')->nullable();
            $table->text('before_nav_html')->nullable();
            $table->text('after_nav_html')->nullable();
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
