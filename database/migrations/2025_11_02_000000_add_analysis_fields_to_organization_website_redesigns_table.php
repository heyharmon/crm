<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_website_redesigns', function (Blueprint $table) {
            $table->decimal('tag_difference_score', 8, 6)->nullable()->after('after_head_html');
            $table->decimal('class_difference_score', 8, 6)->nullable()->after('tag_difference_score');
            $table->decimal('asset_difference_score', 8, 6)->nullable()->after('class_difference_score');
            $table->decimal('composite_score', 8, 6)->nullable()->after('asset_difference_score');
            $table->decimal('statistical_threshold', 8, 6)->nullable()->after('composite_score');
            $table->json('before_tag_counts')->nullable()->after('statistical_threshold');
            $table->json('after_tag_counts')->nullable()->after('before_tag_counts');
        });
    }

    public function down(): void
    {
        Schema::table('organization_website_redesigns', function (Blueprint $table) {
            $table->dropColumn([
                'tag_difference_score',
                'class_difference_score',
                'asset_difference_score',
                'composite_score',
                'statistical_threshold',
                'before_tag_counts',
                'after_tag_counts',
            ]);
        });
    }
};
