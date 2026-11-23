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
        Schema::create('borrow_records', function (Blueprint $table) {
    $table->id();
    $table->string('reference', 25)->unique();
    $table->string('borrower_name', 100);
    $table->string('borrower_id_number', 40);
    $table->foreignId('item_id')->constrained('items');
    $table->unsignedInteger('quantity');
    $table->timestamp('date_borrowed')->useCurrent();
    $table->timestamp('expected_return_date');
    $table->timestamp('actual_return_date')->nullable();
    $table->enum('status', ['Borrowed', 'Returned'])->default('Borrowed');
    $table->string('remarks', 300)->default('');
    $table->foreignId('recorded_by')->constrained('users');
    $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->index(['status', 'expected_return_date']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrow_records');
    }
};
