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
        Schema::create('expense_participants', function (Blueprint $table) {
            $table->id();
            // expense_id is intentionally NOT indexed on the problems branch. The
            // N+1 fix on `solutions` eager-loads participants with
            // `WHERE expense_id IN (...)`, and an index here is what makes that
            // lookup fast — so the index is added there, with EXPLAIN before/after.
            $table->unsignedBigInteger('expense_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('share_amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_participants');
    }
};
