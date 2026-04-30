<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_batches', function (Blueprint $table) {
            $table->id();
            $table->string('bank', 64);
            $table->date('process_date');
            $table->timestamps();

            $table->unique(['bank', 'process_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_batches');
    }
};
