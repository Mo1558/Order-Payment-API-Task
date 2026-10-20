<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Payments\ProcessPaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends BaseApiController
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Retrieve all payments with optional filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $query = Payment::query()->latest('id');

        if ($request->filled('status')) {
            $query->status($request->input('status'));
        }

        if ($request->filled('payment_method')) {
            $query->paymentMethod($request->input('payment_method'));
        }

        if ($request->filled('order_id')) {
            $query->forOrder((int) $request->input('order_id'));
        }

        $payments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Payments retrieved successfully.',
            'data' => PaymentResource::collection($payments),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'last_page' => $payments->lastPage(),
                'has_more' => $payments->hasMorePages(),
            ],
        ]);
    }

    /**
     * Retrieve payment details for a specific payment ID.
     */
    public function show(int $id): JsonResponse
    {
        $payment = Payment::with('order')->find($id);

        if (! $payment) {
            return $this->sendError('Payment not found.', [], 404);
        }

        return $this->sendResponse(
            new PaymentResource($payment),
            'Payment retrieved successfully.'
        );
    }

    /**
     * Retrieve payment details for a specific order.
     */
    public function orderPayments(int $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (! $order) {
            return $this->sendError('Order not found.', [], 404);
        }

        $payments = $order->payments()->latest('id')->get();

        return $this->sendResponse(
            PaymentResource::collection($payments),
            "Payments for Order #{$order->order_number} retrieved successfully."
        );
    }

    /**
     * Simulate payment processing for an order.
     *
     * Business Rule: Payments can only be processed for orders in the 'confirmed' status.
     */
    public function store(ProcessPaymentRequest $request): JsonResponse
    {
        $order = Order::find($request->validated('order_id'));

        if (! $order) {
            return $this->sendError('Order not found.', [], 404);
        }

        $payment = $this->paymentService->processPayment(
            order: $order,
            paymentMethod: $request->validated('payment_method'),
            amount: $request->validated('amount') ? (float) $request->validated('amount') : null,
            details: $request->validated('details', []) ?? []
        );

        $message = $payment->isSuccessful()
            ? 'Payment processed successfully.'
            : 'Payment failed: '.($payment->failure_reason ?? 'Declined by gateway.');

        return $this->sendCreated(
            new PaymentResource($payment),
            $message
        );
    }
}
