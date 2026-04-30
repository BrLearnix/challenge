<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('payment_code')->unique();
            $table->string('customer_document');
            /** Stored in minor units (e.g. cents) to avoid floating-point drift. */
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->text('description')->nullable();
            $table->timestamp('paid_at')->nullable()->index();
            $table->string('observed_reason', 512)->nullable();
            /** Result of bank closing reconciliation vs realtime confirmation. */
            $table->string('reconciliation_match', 32)->nullable()->index();
            $table->timestamp('settled_at')->nullable()->index();
            $table->timestamps();

            $table->index(['merchant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
