<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class StrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = array_unique(array_map(fn (string $key): string => explode('.', $key)[0], array_keys($this->rules())));
            foreach (array_keys($this->all()) as $key) {
                if (! in_array($key, $allowed, true) && $key !== '_method') {
                    $validator->errors()->add($key, "Unknown field: {$key}");
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    protected function passwordRules(int $minimum = 8): array
    {
        return ['required', 'string', 'min:'.$minimum, function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && strlen($value) > 72) {
                $fail('The password must not exceed 72 UTF-8 bytes.');
            }
        }];
    }
}
