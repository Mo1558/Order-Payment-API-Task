<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_address',
        'notes',
        'status',
        'total_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    public function isConfirmed(): bool
    {
        return $this->status === OrderStatus::CONFIRMED;
    }

    public function isPayable(): bool
    {
        return $this->isConfirmed();
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()
            ->where('status', PaymentStatus::SUCCESSFUL->value)
            ->sum('amount');
    }

    public function isFullyPaid(): bool
    {
        return $this->paidAmount() >= (float) $this->total_amount && (float) $this->total_amount > 0;
    }

    public function recalculateTotal(): self
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();

        return $this;
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! empty($status)) {
            $query->where('status', $status);
        }

        return $query;
    }
}
