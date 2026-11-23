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
        Schema::create('activity_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('actor_name')->default('System');
    $table->string('actor_username')->default('');
    $table->string('actor_role')->default('');
    $table->string('action', 160);
    $table->string('entity_type', 40)->default('');
    $table->string('entity_id')->default('');
    $table->string('entity_label', 160)->default('');
    $table->string('details', 500)->default('');
    $table->string('ip', 45)->default('');
    $table->text('user_agent')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
