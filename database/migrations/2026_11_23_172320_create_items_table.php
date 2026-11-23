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
        Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('item_code', 20)->unique();
    $table->string('name', 120);
    $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
    $table->string('description', 500)->default('');
    $table->unsignedInteger('quantity')->default(0);
    $table->string('unit', 20)->default('pcs');
    $table->unsignedInteger('minimum_stock')->default(0);
    $table->string('location', 80)->default('');
    $table->string('condition', 50)->default('Good');
    $table->boolean('is_active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['quantity', 'is_active']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
