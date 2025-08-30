<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Drop existing foreign key and recreate with null on delete
            try {
                $table->dropForeign(['organization_category_id']);
            } catch (\Throwable $e) {
                // Some drivers or states may not have the constraint; continue
            }

            $table->foreign('organization_category_id')
                ->references('id')
                ->on('organization_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            try {
                $table->dropForeign(['organization_category_id']);
            } catch (\Throwable $e) {
                // ignore if not present
            }
            // Recreate without explicit on delete (defaults to restrict/no action)
            $table->foreign('organization_category_id')
                ->references('id')
                ->on('organization_categories');
        });
    }
};

