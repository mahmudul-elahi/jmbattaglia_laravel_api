<?php

namespace App\Http\Requests;

class ContentRequest extends StrictRequest
{
    public function rules(): array
    {
        if ($this->isMethod('GET')) {
            return ['search' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1|max:100000', 'limit' => 'sometimes|integer|min:1|max:100', 'kind' => 'sometimes|in:EDUCATION,RESOURCE,TOOLTIP', 'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION', 'status' => $this->is('api/v1/admin/*') ? 'sometimes|in:DRAFT,PUBLISHED,ARCHIVED' : 'prohibited'];
        }
        if (in_array($this->route()->getActionMethod(), ['publish', 'draft', 'archive'])) {
            return ['version' => 'required|integer|min:1'];
        }
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return ['key' => $required.'|string|max:100|regex:/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', 'kind' => $required.'|in:EDUCATION,RESOURCE,TOOLTIP', 'title' => $required.'|string|max:200', 'body' => 'sometimes|string|max:20000', 'url' => 'sometimes|nullable|url:https|max:2000', 'category' => 'sometimes|nullable|in:RESALE_HOME,NEW_CONSTRUCTION', 'sortOrder' => 'sometimes|integer|min:0|max:10000', 'mediaIds' => 'sometimes|array|max:10', 'mediaIds.*' => 'integer|distinct|exists:media_assets,id', 'version' => $this->isMethod('PATCH') ? 'required|integer|min:1' : 'prohibited'];
    }
}
