<?php

namespace App\Http\Requests;

class ProfileRequest extends StrictRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $data = $this->all();
        foreach (['name', 'firstName', 'email', 'currentPassword', 'newPassword', 'confirmPassword'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === null || (is_string($data[$key]) && ($data[$key] === '' || (in_array($key, ['name', 'firstName', 'email']) && trim($data[$key]) === ''))))) {
                unset($data[$key]);
            }
        }
        if (isset($data['removeProfilePicture']) && in_array($data['removeProfilePicture'], ['true', 'false'], true)) {
            $data['removeProfilePicture'] = $data['removeProfilePicture'] === 'true';
        }
        $this->replace($data);
    }

    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return [];
        }

        return ['name' => 'sometimes|string|min:1|max:100|prohibits:firstName,lastName', 'firstName' => 'sometimes|string|min:1|max:100', 'lastName' => 'sometimes|nullable|string|max:100', 'email' => 'sometimes|email|max:254', 'currentPassword' => 'sometimes|string|max:4096', 'newPassword' => ['sometimes', ...array_filter($this->passwordRules(12), fn ($rule) => $rule !== 'required')], 'confirmPassword' => 'required_with:newPassword|same:newPassword', 'profilePicture' => 'sometimes|file|image|mimetypes:image/jpeg,image/png|max:5120|prohibits:avatarMediaId', 'removeProfilePicture' => 'sometimes|boolean', 'avatarMediaId' => 'sometimes|nullable|integer|exists:media_assets,id'];
    }
}
