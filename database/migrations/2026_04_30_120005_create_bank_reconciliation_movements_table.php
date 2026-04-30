<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliation_movements', function (Blueprint $table) {
            $table->id();
            // Short FK name: MySQL identifier limit is 64 chars; auto name exceeds it.
            $table->unsignedBigInteger('bank_reconciliation_batch_id');
            $table->foreign('bank_reconciliation_batch_id', 'rec_mov_batch_fk')
                ->references('id')
                ->on('bank_reconciliation_batches')
                ->cascadeOnDelete();
            $table->string('bank_movement_id');
            $table->string('bank_transaction_id')->nullable()->index();
            $table->string('payment_code')->nullable()->index();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestamp('paid_at')->nullable();
            $table->string('outcome', 32)->default('PENDING')->index();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['bank_reconciliation_batch_id', 'bank_movement_id'], 'reconciliation_batch_movement_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliation_movements');
    }
};
