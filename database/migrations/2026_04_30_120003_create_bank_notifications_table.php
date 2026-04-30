<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('bank_transaction_id')->unique();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_code')->index();
            $table->json('payload');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reported_payment_status', 32);
            $table->timestamp('paid_at')->nullable();
            $table->string('processing_outcome', 64)->nullable()->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_notifications');
    }
};
