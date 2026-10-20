<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    /**
     * Send successful JSON response.
     */
    protected function sendResponse(mixed $data = null, ?string $message = null, int $code = 200): JsonResponse
    {
        $response = [
            'success' => true,
        ];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Send created (201) JSON response.
     */
    protected function sendCreated(mixed $data = null, ?string $message = 'Resource created successfully.'): JsonResponse
    {
        return $this->sendResponse($data, $message, 201);
    }

    /**
     * Send error JSON response.
     *
     * @param  array<string, mixed>  $errors
     */
    protected function sendError(string $message, array $errors = [], int $code = 400): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
