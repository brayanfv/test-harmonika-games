<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePeriodClosingRequest;
use App\Models\PeriodClosing;
use App\Services\PeriodClosingDispatcher;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodClosingController extends Controller
{
    public function __construct(
        private readonly PeriodClosingDispatcher $dispatcher,
    ) {}

    public function store(StorePeriodClosingRequest $request): JsonResponse
    {
        $period = $request->validated();
        $existingClosing = $request->user()
            ->periodClosings()
            ->whereDate('start_date', $period['start_date'])
            ->whereDate('end_date', $period['end_date']);

        $periodClosing = $existingClosing->first();

        if ($periodClosing === null) {
            try {
                $periodClosing = $request->user()->periodClosings()->create([
                    ...$period,
                    'status' => PeriodClosing::STATUS_PENDING,
                ]);
            } catch (UniqueConstraintViolationException) {
                $periodClosing = $existingClosing->firstOrFail();
            }
        }

        $this->dispatcher->releaseStaleProcessing($periodClosing);
        $this->dispatcher->dispatchIfAvailable($periodClosing, retryFailed: true);
        $periodClosing->refresh();

        return response()->json([
            'message' => $this->responseMessage($periodClosing),
            'period_closing' => $periodClosing,
        ], 202);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $periodClosing = $request->user()
            ->periodClosings()
            ->findOrFail($id);

        $this->dispatcher->releaseStaleProcessing($periodClosing);
        $this->dispatcher->dispatchIfAvailable($periodClosing);
        $periodClosing->refresh();

        return response()->json($periodClosing);
    }

    private function responseMessage(PeriodClosing $periodClosing): string
    {
        return match ($periodClosing->status) {
            PeriodClosing::STATUS_SENT => 'Este fechamento já foi enviado por e-mail.',
            PeriodClosing::STATUS_FAILED => 'Não foi possível colocar o fechamento na fila.',
            default => 'Fechamento aceito para processamento.',
        };
    }
}
