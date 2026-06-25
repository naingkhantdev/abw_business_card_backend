<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'industry' => $this->industry,
            'business_type' => $this->business_type,
            'description' => $this->description,
            'address' => $this->address,
            'website' => $this->website,
            'phone' => $this->phone,
            'email' => $this->email,
            'created_by' => $this->created_by,
            'is_deleted' => $this->deleted_at !== null,
            'socials' => CompanySocialResource::collection(
                $this->relationLoaded('socials') ? $this->socials : []
            ),
        ];
    }
}

