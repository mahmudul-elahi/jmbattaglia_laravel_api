<?php

namespace App\Http\Requests;

class PackageRequest extends StrictRequest
{
    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['search' => 'sometimes|string|max:100', 'kind' => 'sometimes|in:PACKAGE,SERVICE,ADDON', 'tab' => 'sometimes|in:packages,add-ons,addons', 'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION', 'page' => 'sometimes|integer|min:1', 'limit' => 'sometimes|integer|min:1|max:100'];
        }
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return ['name' => ($this->route()->getActionMethod() === 'store' ? 'required' : 'sometimes').'|string|min:1|max:150', 'description' => 'sometimes|string|max:5000', 'active' => 'sometimes|boolean', 'show' => 'sometimes|boolean', 'best' => 'sometimes|boolean', 'sequence' => 'sometimes|integer|min:0|max:10000', 'services' => 'sometimes|array|max:100', 'services.*' => 'string|max:100|distinct', 'modifiers' => 'sometimes|array|max:100', 'modifiers.*' => 'string|max:100|distinct'];
    }
}
