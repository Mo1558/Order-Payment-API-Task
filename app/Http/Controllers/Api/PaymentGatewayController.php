<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentGatewayController extends BaseApiController
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * List all available payment gateways.
     */
    public function index(): JsonResponse
    {
        $gateways = $this->gatewayManager->getAvailableGateways();

        return $this->sendResponse(
            $gateways,
            'Available payment gateways retrieved successfully.'
        );
    }

    /**
     * Create or update database configuration for a payment gateway.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'display_name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'config' => ['nullable', 'array'],
        ]);

        $gateway = PaymentGateway::updateOrCreate(
            ['name' => $validated['name']],
            [
                'display_name' => $validated['display_name'],
                'is_active' => $validated['is_active'] ?? true,
                'config' => $validated['config'] ?? [],
            ]
        );

        return $this->sendResponse(
            $gateway,
            'Payment gateway configuration saved successfully.'
        );
    }
}
