<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('team_user');
    }

    public function down(): void
    {
        Schema::create('team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('role')->default('member');
            $table->boolean('invitation_accepted')->default(false);
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });
    }
};
