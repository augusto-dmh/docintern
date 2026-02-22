<?php

namespace App\Concerns;

trait DocumentValidationRules
{
    /**
     * Get the validation rules used to validate documents.
     *
     * @return array<string, array<int, string>>
     */
    protected function documentStoreRules(): array
    {
        return [
            'title' => $this->documentTitleRules(),
            'file' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
                'max:102400',
            ],
        ];
    }

    /**
     * Get the validation rules used to update documents.
     *
     * @return array<string, array<int, string>>
     */
    protected function documentUpdateRules(): array
    {
        return [
            'title' => $this->documentTitleRules(),
        ];
    }

    /**
     * Get the validation rules for document title.
     *
     * @return array<int, string>
     */
    protected function documentTitleRules(): array
    {
        return ['required', 'string', 'max:255'];
    }
}
