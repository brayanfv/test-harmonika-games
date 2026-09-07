<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->date('due_date');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sending_at')->nullable();
            $table->string('delivery_token')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['financial_transaction_id', 'type']);
            $table->index(['sent_at', 'cancelled_at', 'dispatched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_reminders');
    }
};
