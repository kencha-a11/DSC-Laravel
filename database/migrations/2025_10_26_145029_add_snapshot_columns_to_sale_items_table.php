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
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'snapshot_name')) {
                $table->string('snapshot_name')->nullable();
            }
            if (!Schema::hasColumn('sale_items', 'snapshot_quantity')) {
                $table->integer('snapshot_quantity')->nullable();
            }
            if (!Schema::hasColumn('sale_items', 'snapshot_price')) {
                $table->decimal('snapshot_price', 10, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['snapshot_name', 'snapshot_quantity', 'snapshot_price']);
        });
    }
};
