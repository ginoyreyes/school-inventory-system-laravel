<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => (string) $this->id,
            'reference'    => $this->reference,
            'type'         => $this->type,
            'itemId'       => $this->item_id ? (string) $this->item_id : null,
            'itemName'     => $this->item?->name ?? 'Deleted item',
            'itemCode'     => $this->item?->item_code ?? '',
            'quantity'     => $this->quantity,
            'balanceAfter' => $this->balance_after,
            'date'         => $this->date?->toISOString(),
            'supplier'     => $this->supplier ?? '',
            'reason'       => $this->reason ?? '',
            'remarks'      => $this->remarks ?? '',
            'recordedBy'   => $this->recorder ? [
                'id'       => (string) $this->recorder->id,
                'name'     => $this->recorder->name,
                'username' => $this->recorder->username,
            ] : null,
        ];
    }
}