<?php

namespace App\Http\Requests;

class LookupRequest extends StrictRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'start' => ['email' => 'required|email|max:254', 'customerType' => 'required|in:HOME_BUYER,HOME_OWNER,BUYERS_AGENT'],
            'verify' => ['challengeId' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'code' => ['required', 'string', 'regex:/^[0-9]{6}$/']],
            'client' => ['firstName' => 'required|string|max:100', 'lastName' => 'required|string|max:100', 'email' => 'required|email|max:254', 'phone' => ['required', 'string', 'regex:/^[+()0-9 .-]{7,30}$/']],
            default => [],
        };
    }
}
