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
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();

            // Keep cascade on user deletion
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Allow product_id to become null when product is deleted
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            $table->enum('action', ['created', 'update', 'restock', 'deducted', 'deleted', 'adjusted'])
                ->default('adjusted');

            $table->integer('quantity_change')->nullable();
            $table->string('snapshot_name')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_logs');
    }
};
