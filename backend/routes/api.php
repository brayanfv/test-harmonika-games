<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\FinancialTransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);
    Route::get('/contacts/{id}', [ContactController::class, 'show']);
    Route::put('/contacts/{id}', [ContactController::class, 'update']);
    Route::delete('/contacts/{id}', [ContactController::class, 'destroy']);

    Route::get('/transactions', [FinancialTransactionController::class, 'index']);
    Route::post('/transactions', [FinancialTransactionController::class, 'store']);
    Route::get('/transactions/{id}', [FinancialTransactionController::class, 'show']);
    Route::put('/transactions/{id}', [FinancialTransactionController::class, 'update']);
    Route::delete('/transactions/{id}', [FinancialTransactionController::class, 'destroy']);
    Route::post('/transactions/{id}/pay', [FinancialTransactionController::class, 'pay']);
});