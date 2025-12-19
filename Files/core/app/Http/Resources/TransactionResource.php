<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TransactionResource - API resource for Transaction model
 *
 * Transforms Transaction model data for API responses, ensuring only safe data is exposed.
 * Follows Laravel API Resource best practices.
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'amount' => (float) $this->amount,
            'charge' => (float) $this->charge,
            'trx' => $this->trx,
            'trx_type' => $this->trx_type,
            'remark' => $this->remark,
            'details' => $this->details,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
