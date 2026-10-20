<?php

namespace App\Services\Payment\DTOs;

use App\Enums\PaymentStatus;

class PaymentResult
{
    public function __construct(
        public readonly bool $successful,
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
        public readonly array $rawResponse = []
    ) {}

    public static function success(
        string $transactionId,
        string $message = 'Payment processed successfully.',
        array $rawResponse = []
    ): self {
        return new self(
            successful: true,
            status: PaymentStatus::SUCCESSFUL,
            transactionId: $transactionId,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function failure(
        string $message = 'Payment failed.',
        array $rawResponse = [],
        ?string $transactionId = null
    ): self {
        return new self(
            successful: false,
            status: PaymentStatus::FAILED,
            transactionId: $transactionId,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public static function pending(
        string $transactionId,
        string $message = 'Payment is pending confirmation.',
        array $rawResponse = []
    ): self {
        return new self(
            successful: false,
            status: PaymentStatus::PENDING,
            transactionId: $transactionId,
            message: $message,
            rawResponse: $rawResponse
        );
    }

    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'status' => $this->status->value,
            'transaction_id' => $this->transactionId,
            'message' => $this->message,
            'raw_response' => $this->rawResponse,
        ];
    }
}
