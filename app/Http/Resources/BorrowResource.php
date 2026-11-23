<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BorrowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => (string) $this->id,
            'reference'          => $this->reference,
            'borrowerName'       => $this->borrower_name,
            'borrowerIdNumber'   => $this->borrower_id_number,
            'itemId'             => $this->item_id ? (string) $this->item_id : null,
            'itemName'           => $this->item?->name ?? 'Deleted item',
            'itemCode'           => $this->item?->item_code ?? '',
            'unit'               => $this->item?->unit ?? '',
            'quantity'           => $this->quantity,
            'dateBorrowed'       => $this->date_borrowed?->toISOString(),
            'expectedReturnDate' => $this->expected_return_date?->toISOString(),
            'actualReturnDate'   => $this->actual_return_date?->toISOString(),
            'status'             => $this->status,
            'displayStatus'      => $this->display_status,
            'remarks'            => $this->remarks ?? '',
            'recordedBy'         => $this->recorder ? [
                'id'   => (string) $this->recorder->id,
                'name' => $this->recorder->name,
            ] : null,
            'returnedBy'         => $this->returner ? [
                'id'   => (string) $this->returner->id,
                'name' => $this->returner->name,
            ] : null,
        ];
    }
}