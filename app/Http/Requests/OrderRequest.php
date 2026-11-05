<?php

namespace App\Http\Requests;

class OrderRequest extends QuoteRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        foreach (['contact', 'buyerContact'] as $key) {
            $contact = $this->input($key);
            if (is_array($contact) && is_string($contact['email'] ?? null)) {
                $contact['email'] = mb_strtolower(trim($contact['email']));
                $this->merge([$key => $contact]);
            }
        }
    }

    public function rules(): array
    {
        $rules = parent::rules() + ['quoteId' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'], 'customerType' => 'required|in:HOME_BUYER,HOME_OWNER,BUYERS_AGENT', 'date' => 'required|date_format:Y-m-d', 'time' => ['required', 'string', 'regex:/^(?:[01][0-9]|2[0-3]):(?:00|15|30|45)$/'], 'inspectorId' => 'required|string|max:100'];
        foreach (['contact', 'buyerContact'] as $key) {
            $rules[$key] = ($key === 'contact' ? 'required' : 'required_if:customerType,BUYERS_AGENT').'|array:firstName,lastName,email,phone';
            foreach (['firstName', 'lastName'] as $name) {
                $rules[$key.'.'.$name] = 'required_with:'.$key.'|string|min:1|max:100';
            }
            $rules[$key.'.email'] = 'required_with:'.$key.'|email|max:254';
            $rules[$key.'.phone'] = ['required_with:'.$key, 'string', 'regex:/^[+()0-9 .-]{7,30}$/'];
        }

        return $rules;
    }
}
