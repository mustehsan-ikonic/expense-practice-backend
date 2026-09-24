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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            // NOTE (problems branch): relationship columns are plain integers with
            // no foreign-key constraints and no indexes. This models a common
            // real-world schema that grew without deliberate indexing, and lets the
            // `solutions` branch demonstrate adding indexes only where the access
            // pattern needs them (MySQL auto-indexes FK columns, which would hide
            // that lesson).
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
