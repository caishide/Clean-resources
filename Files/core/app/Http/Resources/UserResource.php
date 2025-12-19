<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * UserResource - API resource for User model
 *
 * Transforms User model data for API responses, ensuring only safe data is exposed.
 * Follows Laravel API Resource best practices.
 */
class UserResource extends JsonResource
{
    /** @var int Verified status constant */
    private const VERIFIED_STATUS = 1;

    /** @var int Two-factor authentication enabled constant */
    private const TWO_FACTOR_ENABLED = 1;

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
            'username' => $this->username,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'full_name' => $this->fullname,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country' => $this->country,
            'address' => $this->address,
            'state' => $this->state,
            'city' => $this->city,
            'zip' => $this->zip,
            'image' => $this->image,
            'balance' => (float) $this->balance,
            'status' => $this->status,
            'email_verified' => $this->ev === self::VERIFIED_STATUS,
            'sms_verified' => $this->sv === self::VERIFIED_STATUS,
            'kyc_verified' => $this->kv === self::VERIFIED_STATUS,
            'two_factor_enabled' => $this->ts === self::TWO_FACTOR_ENABLED,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
