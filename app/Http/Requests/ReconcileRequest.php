<?php

namespace App\Http\Requests;

class ReconcileRequest extends StrictRequest
{
    public function rules(): array
    {
        return ['remoteOrderId' => 'required|string|max:100'];
    }
}
