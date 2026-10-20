<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference',
        'order_id',
        'amount',
        'currency',
        'payment_method',
        'status',
        'transaction_id',
        'gateway_response',
        'failure_reason',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::SUCCESSFUL;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function scopePaymentMethod(Builder $query, ?string $method): Builder
    {
        if (! empty($method)) {
            $query->where('payment_method', $method);
        }

        return $query;
    }

    public function scopeForOrder(Builder $query, ?int $orderId): Builder
    {
        if (! empty($orderId)) {
            $query->where('order_id', $orderId);
        }

        return $query;
    }
}
