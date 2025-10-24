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
        Schema::create('website_rating_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedTinyInteger('score');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_website_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('website_rating_option_id')
                ->constrained('website_rating_options')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index('website_rating_option_id');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->decimal('website_rating_average', 4, 2)->nullable()->after('website');
            $table->unsignedInteger('website_rating_count')->default(0)->after('website_rating_average');
            $table->string('website_rating_summary', 50)->nullable()->after('website_rating_count');
        });

        if (Schema::hasColumn('organizations', 'website_rating')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('website_rating');
            });
        }

        DB::table('website_rating_options')->insert([
            [
                'name' => 'Good',
                'slug' => 'good',
                'score' => 3,
                'description' => 'Website meets expectations and offers a solid experience.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Okay',
                'slug' => 'okay',
                'score' => 2,
                'description' => 'Website is usable but could benefit from improvements.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Bad',
                'slug' => 'bad',
                'score' => 1,
                'description' => 'Website creates a poor experience or is unusable.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'website_rating_summary')) {
                $table->dropColumn('website_rating_summary');
            }
            if (Schema::hasColumn('organizations', 'website_rating_count')) {
                $table->dropColumn('website_rating_count');
            }
            if (Schema::hasColumn('organizations', 'website_rating_average')) {
                $table->dropColumn('website_rating_average');
            }
            $table->enum('website_rating', ['good', 'okay', 'bad'])->nullable()->after('website');
        });

        Schema::dropIfExists('organization_website_ratings');
        Schema::dropIfExists('website_rating_options');
    }
};

