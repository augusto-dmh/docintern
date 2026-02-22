<?php

namespace App\Services\Processing;

use App\Models\Document;
use RuntimeException;

class SimulatedOcrProvider implements OcrProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     extracted_text: string,
     *     payload: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     classification_hint: string
     * }
     */
    public function extract(Document $document, array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null)
            ? $payload['metadata']
            : [];

        if (($metadata['simulate_ocr_failure'] ?? false) === true) {
            throw new RuntimeException('Simulated OCR failure requested by payload metadata.');
        }

        $hint = $this->classificationRoutingResolver->resolveTypeHint(
            is_string($metadata['classification_hint'] ?? null) ? $metadata['classification_hint'] : null,
            $document->file_name,
        );

        $extractedText = sprintf(
            'Simulated OCR extraction for %s (%s).',
            $document->title,
            $document->file_name,
        );

        return [
            'provider' => 'simulated',
            'extracted_text' => $extractedText,
            'payload' => [
                'lines' => [$extractedText],
                'document_name' => $document->file_name,
                'simulated' => true,
            ],
            'metadata' => [
                'simulated' => true,
                'classification_hint' => $hint,
            ],
            'classification_hint' => $hint,
        ];
    }
}
