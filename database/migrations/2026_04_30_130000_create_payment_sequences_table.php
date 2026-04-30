<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_sequences', function (Blueprint $table) {
            $table->id();
            $table->date('sequence_date')->unique();
            /** Last issued serial for that calendar day (LTP-YYYYMMDD-XXXXXX). */
            $table->unsignedInteger('last_serial')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_sequences');
    }
};
