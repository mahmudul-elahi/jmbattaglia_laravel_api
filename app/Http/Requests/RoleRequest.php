<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RoleRequest extends StrictRequest
{
    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : ($this->route()->getActionMethod() === 'grants' ? ['permissions' => 'required|array', 'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'name')->where('guard_name', 'web')]] : ['userId' => 'required|integer|exists:users,id', 'role' => 'required|in:ADMIN,USER']);
    }
}
