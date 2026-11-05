<?php

namespace App\Http\Requests;

class MediaRequest extends StrictRequest
{
    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'index' => ['page' => 'sometimes|integer|min:1', 'limit' => 'sometimes|integer|min:1|max:100'],
            'upload' => ['file' => 'required|file|mimetypes:image/jpeg,image/png,video/mp4|max:51200', 'altText' => 'sometimes|string|max:300'],
            'signed' => ['filename' => ['required', 'string', 'max:200', 'regex:/^[\pL\pN ._-]+\.(jpg|jpeg|png|mp4)$/iu'], 'mimeType' => 'required|in:image/jpeg,image/png,video/mp4', 'sizeBytes' => 'required|integer|min:1|max:52428800', 'checksum' => ['required', 'string', 'regex:/^[A-Za-z0-9+\/]{43}=$/'], 'altText' => 'sometimes|string|max:300'],
            default => [],
        };
    }
}
