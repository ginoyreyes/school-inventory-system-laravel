<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => (string) $this->id,
            'name'          => $this->name,
            'description'   => $this->description ?? '',
            'itemCount'     => (int) ($this->items_count ?? 0),
            'totalQuantity' => (int) ($this->items_sum_quantity ?? 0),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}