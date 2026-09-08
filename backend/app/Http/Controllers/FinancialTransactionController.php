<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFinancialTransactionRequest;
use App\Http\Requests\UpdateFinancialTransactionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FinancialTransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()
            ->financialTransactions()
            ->with('contact');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $transactions = $query
            ->latest('due_date')
            ->get();

        return response()->json($transactions);
    }

    public function store(StoreFinancialTransactionRequest $request): JsonResponse
    {
        $transaction = $request->user()
            ->financialTransactions()
            ->create([
                ...$request->validated(),
                'status' => 'pending',
            ]);

        return response()->json($transaction->load('contact'), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transaction = $request->user()
            ->financialTransactions()
            ->with('contact')
            ->findOrFail($id);

        return response()->json($transaction);
    }

    public function update(
        UpdateFinancialTransactionRequest $request,
        int $id
    ): JsonResponse {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $transaction = $request->user()
                ->financialTransactions()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transaction->status === 'paid') {
                return response()->json([
                    'message' => 'Paid transactions cannot be updated.',
                ], 422);
            }

            $transaction->update($request->validated());

            return response()->json($transaction->load('contact'));
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $transaction = $request->user()
                ->financialTransactions()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transaction->status === 'paid') {
                return response()->json([
                    'message' => 'Paid transactions cannot be deleted.',
                ], 422);
            }

            $transaction->delete();

            return response()->json(null, 204);
        });
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id): JsonResponse {
            $transaction = $request->user()
                ->financialTransactions()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($transaction->status === 'paid') {
                return response()->json([
                    'message' => 'Transaction is already paid.',
                ], 422);
            }

            $transaction->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return response()->json($transaction->load('contact'));
        });
    }
}
