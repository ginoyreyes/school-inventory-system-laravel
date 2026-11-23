<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => (string) $this->id,
            'name'          => $this->name,
            'description'   => $this->description ?? '',
            'area'          => $this->area ?? '',
            'itemCount'     => (int) ($this->item_count ?? 0),
            'totalQuantity' => (int) ($this->total_quantity ?? 0),
            'createdAt'     => $this->created_at?->toISOString(),
        ];
    }
}