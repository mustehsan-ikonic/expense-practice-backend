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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            // Plain integer columns, no FK constraints, no indexes on the
            // `problems` branch (see accounts migration note).
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            // pending | processing | processed | failed (cast to ExpenseStatus enum)
            $table->string('status')->default('pending');
            // Present but NOT unique on the problems branch: nothing stops a retry
            // from creating a duplicate expense. The solutions branch adds the
            // unique index that provides the real integrity guarantee.
            $table->string('idempotency_key')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
