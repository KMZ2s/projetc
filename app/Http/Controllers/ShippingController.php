<?php

namespace App\Http\Controllers;

use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function __construct(protected ShippingService $shipping) {}

    /**
     * POST /api/shipping/calculate
     * Body: { cep: "01000-000" }
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cep' => ['required', 'string', 'min:8', 'max:9'],
        ]);

        $result = $this->shipping->calculateForCep($validated['cep']);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}