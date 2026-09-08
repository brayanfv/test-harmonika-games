<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->index(
                ['status', 'due_date', 'id'],
                'financial_transactions_status_due_date_id_index'
            );
            $table->index(
                ['user_id', 'status', 'due_date'],
                'financial_transactions_user_status_due_date_index'
            );
            $table->index(
                ['user_id', 'status', 'paid_at'],
                'financial_transactions_user_status_paid_at_index'
            );
        });

        Schema::table('transaction_reminders', function (Blueprint $table) {
            $table->dropUnique('transaction_reminders_financial_transaction_id_type_unique');
            $table->unique(
                ['financial_transaction_id', 'type', 'due_date'],
                'transaction_reminders_transaction_type_due_date_unique'
            );
        });

        Schema::table('period_closings', function (Blueprint $table) {
            $table->index(
                ['status', 'processing_at'],
                'period_closings_status_processing_at_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('period_closings', function (Blueprint $table) {
            $table->dropIndex('period_closings_status_processing_at_index');
        });

        Schema::table('transaction_reminders', function (Blueprint $table) {
            $table->dropUnique('transaction_reminders_transaction_type_due_date_unique');
            $table->unique(['financial_transaction_id', 'type']);
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropIndex('financial_transactions_status_due_date_id_index');
            $table->dropIndex('financial_transactions_user_status_due_date_index');
            $table->dropIndex('financial_transactions_user_status_paid_at_index');
        });
    }
};
