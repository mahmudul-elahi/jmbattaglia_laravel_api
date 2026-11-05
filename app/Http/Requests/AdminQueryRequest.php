<?php

namespace App\Http\Requests;

class AdminQueryRequest extends StrictRequest
{
    public function rules(): array
    {
        return ['search' => 'sometimes|string|max:100', 'page' => 'sometimes|integer|min:1', 'limit' => 'sometimes|integer|min:1|max:100', 'category' => 'sometimes|in:RESALE_HOME,NEW_CONSTRUCTION', 'status' => $this->route()->getActionMethod() === 'submissions' ? 'sometimes|in:PROCESSING,SUBMITTED,FAILED,REQUIRES_REVIEW' : 'sometimes|in:DRAFT,PUBLISHED,ARCHIVED'];
    }
}
