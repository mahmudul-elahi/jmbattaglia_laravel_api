<?php

namespace App\Http\Requests;

class AvailabilityRequest extends StrictRequest
{
    public function rules(): array
    {
        return ['quoteId' => ['sometimes', 'string', 'regex:/^[a-f0-9]{64}$/'], 'date' => 'required|date_format:Y-m-d', 'days' => 'sometimes|integer|min:1|max:14'];
    }
}
