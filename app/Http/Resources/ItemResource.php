<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => (string) $this->id,
            'itemCode'     => $this->item_code,
            'name'         => $this->name,
            'category'     => $this->category?->name ?? 'No category',
            'categoryId'   => $this->category_id ? (string) $this->category_id : null,
            'description'  => $this->description ?? '',
            'quantity'     => $this->quantity,
            'unit'         => $this->unit,
            'minimumStock' => $this->minimum_stock,
            'location'     => $this->location ?? '',
            'condition'    => $this->condition,
            'status'       => $this->status,
            'isActive'     => $this->is_active,
            'dateAdded'    => $this->created_at?->toISOString(),
            'lastUpdated'  => $this->updated_at?->toISOString(),
        ];
    }
}