<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CreditService $creditService
    ) {}

    public function balance(Request $request): JsonResponse
    {
        return $this->successResponse(
            'Credit balance retrieved successfully.',
            ['balance' => $this->creditService->getBalance($request->user())]
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $request->user()
            ->creditTransactions()
            ->latest()
            ->paginate(20);

        return $this->successResponse(
            'Credit transactions retrieved successfully.',
            [
                'transactions' => $transactions->items(),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                ],
            ]
        );
    }
}
