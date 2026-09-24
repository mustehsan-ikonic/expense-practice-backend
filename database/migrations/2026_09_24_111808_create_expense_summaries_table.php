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
        Schema::create('expense_summaries', function (Blueprint $table) {
            $table->id();
            // One summary per expense. On the problems branch this is NOT unique,
            // so a non-idempotent job that runs twice creates duplicate summaries.
            // The solutions branch makes the job idempotent AND adds a unique index
            // here as the database-level backstop.
            $table->unsignedBigInteger('expense_id');
            $table->unsignedInteger('participant_count');
            $table->decimal('per_head_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_summaries');
    }
};
