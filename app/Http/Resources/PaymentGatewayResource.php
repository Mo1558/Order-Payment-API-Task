<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentGatewayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? null,
            'name' => $this->name ?? $this['name'] ?? null,
            'display_name' => $this->display_name ?? $this['display_name'] ?? null,
            'is_active' => (bool) ($this->is_active ?? $this['is_active'] ?? true),
        ];
    }
}
