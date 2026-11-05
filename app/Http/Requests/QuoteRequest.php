<?php

namespace App\Http\Requests;

class QuoteRequest extends StrictRequest
{
    public function rules(): array
    {
        $properties = ['address1', 'address2', 'city', 'state', 'zip', 'yearBuilt', 'squareFeet', 'foundationType', 'foundationDescription', 'clientAttending', 'occupied', 'gatedCommunity', 'alarmSystem', 'poolSpa', 'sewageSystem', 'waterSystem', 'utilitiesOn', 'referral', 'gateCode', 'dueDiligence', 'dueDiligenceDate', 'notes', 'clientAttendance', 'occupancy', 'accessType', 'alarmStatus', 'poolSpaType', 'utilityStatus'];
        $rules = ['category' => 'required|in:RESALE_HOME,NEW_CONSTRUCTION', 'serviceIds' => 'sometimes|array|max:30', 'serviceIds.*' => ['distinct', function (string $attribute, mixed $value, \Closure $fail): void {
            if (! ((is_int($value) && $value > 0) || (is_string($value) && $value !== '' && mb_strlen($value) <= 100))) {
                $fail('Use an integer local add-on ID or a string ISN service ID.');
            }
        }], 'packageId' => 'sometimes|string|max:100', 'orderTypeId' => 'sometimes|string|max:100', 'property' => 'required|array:'.implode(',', $properties),
            'property.address1' => 'required|string|max:200', 'property.address2' => 'sometimes|nullable|string|max:200', 'property.city' => 'required|string|max:100', 'property.state' => ['required', 'string', 'regex:/^(AL|AK|AZ|AR|CA|CO|CT|DE|DC|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY)$/'], 'property.zip' => ['required', 'string', 'regex:/^[0-9]{5}(?:-[0-9]{4})?$/'], 'property.yearBuilt' => 'required|integer:strict|min:1600|max:'.(now()->year + 1), 'property.squareFeet' => 'required|integer:strict|min:1|max:1000000', 'property.foundationType' => 'required|string|max:100', 'property.foundationDescription' => 'sometimes|nullable|string|max:250', 'property.sewageSystem' => 'required|in:PUBLIC,SEPTIC,ATU,SHARED_SEPTIC', 'property.waterSystem' => 'required|in:PUBLIC,WELL,SHARED_WELL,CISTERN', 'property.referral' => 'required|string|max:150', 'property.gateCode' => 'sometimes|nullable|string|max:100', 'property.dueDiligence' => 'sometimes|nullable|string|max:500', 'property.dueDiligenceDate' => 'sometimes|nullable|date_format:Y-m-d', 'property.notes' => 'sometimes|nullable|string|max:3000'];
        foreach (['clientAttending', 'occupied', 'gatedCommunity', 'alarmSystem', 'poolSpa', 'utilitiesOn'] as $key) {
            $rules['property.'.$key] = 'required|boolean:strict';
        }
        foreach (['clientAttendance' => 'FULL_INSPECTION,FINAL_WALKTHROUGH,NOT_ATTENDING,AGENT_ATTENDING', 'occupancy' => 'OCCUPIED,VACANT_UNFURNISHED,VACANT_STAGED,NEW_CONSTRUCTION', 'accessType' => 'OPEN,GATE_CODE,GUARD,INTERCOM', 'alarmStatus' => 'NONE,DISARMED,CODE_REQUIRED,UNKNOWN', 'poolSpaType' => 'NONE,IN_GROUND,ABOVE_GROUND,HOT_TUB,COMBINATION', 'utilityStatus' => 'ALL_ON,ELECTRIC_OFF,WATER_OFF,GAS_OFF,MULTIPLE_OFF'] as $key => $values) {
            $rules['property.'.$key] = 'sometimes|in:'.$values;
        }

        return $rules;
    }
}
