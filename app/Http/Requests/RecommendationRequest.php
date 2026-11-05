<?php

namespace App\Http\Requests;

use Illuminate\Support\Arr;

class RecommendationRequest extends StrictRequest
{
    public function rules(): array
    {
        if ($this->isMethod('GET') || $this->isMethod('DELETE')) {
            return [];
        }
        if ($this->is('api/v1/portal/*')) {
            $quote = new QuoteRequest;

            return Arr::except($quote->rules(), ['serviceIds', 'serviceIds.*', 'packageId', 'orderTypeId']) + ['selectedIds' => 'sometimes|array|max:30', 'selectedIds.*' => 'integer|distinct'];
        }

        return ['itemId' => 'required|integer|exists:catalog_items,id', 'reason' => 'required|string|max:500', 'conditions' => 'required|array:requiresPoolSpa,waterSystem,builtBefore,foundationType', 'conditions.requiresPoolSpa' => 'sometimes|boolean', 'conditions.waterSystem' => 'sometimes|in:PUBLIC,WELL,SHARED_WELL,CISTERN', 'conditions.builtBefore' => 'sometimes|integer|min:1600|max:2200', 'conditions.foundationType' => 'sometimes|string|max:100', 'enabled' => 'sometimes|boolean', 'sortOrder' => 'sometimes|integer|min:0|max:10000'];
    }
}
