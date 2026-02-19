<?php

namespace App\Concerns;

use App\Models\Client;
use Illuminate\Validation\Rule;

trait ClientValidationRules
{
    /**
     * Get the validation rules used to validate clients.
     *
     * @return array<string, array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>>
     */
    protected function clientRules(?int $clientId = null): array
    {
        return [
            'name' => $this->clientNameRules(),
            'email' => $this->clientEmailRules($clientId),
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Get the validation rules for client name.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function clientNameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules for client email.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function clientEmailRules(?int $clientId = null): array
    {
        return [
            'nullable',
            'email',
            'max:255',
            $clientId === null
                ? Rule::unique(Client::class)->where('tenant_id', tenant()->id)
                : Rule::unique(Client::class)->ignore($clientId)->where('tenant_id', tenant()->id),
        ];
    }
}
