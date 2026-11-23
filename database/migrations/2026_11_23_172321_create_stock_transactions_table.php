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
        Schema::create('stock_transactions', function (Blueprint $table) {
    $table->id();
    $table->string('reference', 25)->unique();
    $table->enum('type', ['Stock In', 'Stock Out', 'Borrowed', 'Returned', 'Adjustment']);
    $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
    $table->unsignedInteger('quantity');
    $table->unsignedInteger('balance_after');
    $table->timestamp('date')->useCurrent();
    $table->string('supplier', 120)->default('');
    $table->string('reason', 160)->default('');
    $table->string('remarks', 300)->default('');
    $table->foreignId('recorded_by')->constrained('users');
    $table->timestamps();
    $table->index(['type', 'date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
