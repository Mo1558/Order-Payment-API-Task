<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Orders\CreateOrderRequest;
use App\Http\Requests\Orders\UpdateOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends BaseApiController
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Retrieve all orders with optional status filter and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);
        $filters = $request->only(['status', 'search', 'user_id']);

        $orders = $this->orderService->getOrders($filters, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully.',
            'data' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
                'has_more' => $orders->hasMorePages(),
            ],
        ]);
    }

    /**
     * Create a new order with items and calculate total.
     */
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createOrder(
            data: $request->validated(),
            user: auth('api')->user()
        );

        return $this->sendCreated(
            new OrderResource($order),
            'Order created successfully.'
        );
    }

    /**
     * Retrieve details for a specific order.
     */
    public function show(int $id): JsonResponse
    {
        $order = Order::with(['items', 'payments', 'user'])->find($id);

        if (! $order) {
            return $this->sendError('Order not found.', [], 404);
        }

        return $this->sendResponse(
            new OrderResource($order),
            'Order retrieved successfully.'
        );
    }

    /**
     * Update existing order details.
     */
    public function update(UpdateOrderRequest $request, int $id): JsonResponse
    {
        $order = Order::with(['items', 'payments'])->find($id);

        if (! $order) {
            return $this->sendError('Order not found.', [], 404);
        }

        $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

        return $this->sendResponse(
            new OrderResource($updatedOrder),
            'Order updated successfully.'
        );
    }

    /**
     * Delete an order (only allowed if no payments are associated).
     */
    public function destroy(int $id): JsonResponse
    {
        $order = Order::with('payments')->find($id);

        if (! $order) {
            return $this->sendError('Order not found.', [], 404);
        }

        $this->orderService->deleteOrder($order);

        return $this->sendResponse(
            null,
            'Order successfully deleted.'
        );
    }
}
