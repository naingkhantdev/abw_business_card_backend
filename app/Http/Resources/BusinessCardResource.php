<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessCardResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => $this->id,
            // If card has its own name (manual entry), use it. Otherwise fallback to User's name.
            'full_name'     => $this->name ?? $this->user?->name,
            'position'      => $this->position,
            'phones'        => $this->phones ?? [],
            'emails'        => $this->emails ?? [],
            'addresses'     => $this->addresses ?? [],
            'bio'           => $this->bio,
            'profile_image' => $this->profile_image,
            
            'card_type'     => $this->card_type,
            'qr_code_data'  => $this->qr_code_data,

            'created_by'    => $this->created_by,
            'updated_by'    => $this->updated_by,
            'deleted_by'    => $this->deleted_by,
            'friend_request_status' => $this->friend_request_status ?? 'none',
            'social_links'  => $this->social_links ?? [],
            
            'is_friend'     => (bool) ($this->is_friend ?? false),
            'friend_status' => $this->friend_status ?? 'none',
            'tag'           => $this->tag,

            'company'       => $this->whenLoaded('company', function () {
                return new CompanyResource($this->company);
            }),

            'user'          => $this->whenLoaded('user', function () {
                return [
                    'id'   => $this->user->id,
                    'name' => $this->user->name,
                ];
            }),
            'request_created_at_myanmar' => $this->request_created_at
                ? $this->request_created_at->copy()->timezone('Asia/Yangon')->toIso8601String()
                : null,
            'request_created_at' => $this->request_created_at?->toDateTimeString(),
            'created_at'    => $this->created_at?->toDateTimeString(),
            'updated_at'    => $this->updated_at?->toDateTimeString(),
        ];
    }
}
