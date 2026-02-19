<?php

namespace App\Concerns;

use App\Models\Matter;
use Illuminate\Validation\Rule;

trait MatterValidationRules
{
    /**
     * Get the validation rules used to validate matters.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function matterRules(?int $matterId = null): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reference_number' => $this->referenceNumberRules($matterId),
            'status' => ['required', 'string', Rule::in(['open', 'closed', 'on_hold'])],
        ];
    }

    /**
     * Get the validation rules for matter reference number.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function referenceNumberRules(?int $matterId = null): array
    {
        return [
            'nullable',
            'string',
            'max:100',
            $matterId === null
                ? Rule::unique(Matter::class)->where('tenant_id', tenant()->id)
                : Rule::unique(Matter::class)->ignore($matterId)->where('tenant_id', tenant()->id),
        ];
    }
}
