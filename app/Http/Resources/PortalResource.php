<?php

namespace App\Http\Resources;

use App\Support\PortalSerializer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $request->is('api/v1/portal/*') ? PortalSerializer::publicModel($this->resource) : PortalSerializer::model($this->resource);
    }
}
