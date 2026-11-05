<?php

namespace App\Http\Requests;

class SettingsRequest extends StrictRequest
{
    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : ['version' => 'required|integer|min:1', 'brandName' => 'sometimes|required|string|max:150', 'helpPhone' => ['sometimes', 'string', 'regex:/^[+()0-9 -]{7,30}$/'], 'welcomeTitle' => 'sometimes|required|string|max:500', 'pricingDisclaimer' => 'sometimes|required|string|max:1000', 'confirmationMessage' => 'sometimes|required|string|max:1000'];
    }
}
