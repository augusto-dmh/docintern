<?php

namespace App\Services\Processing;

use App\Models\Document;
use App\Models\ExtractedData;
use RuntimeException;

class SimulatedClassificationProvider implements ClassificationProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     type: string,
     *     confidence: float,
     *     metadata: array<string, mixed>
     * }
     */
    public function classify(Document $document, ?ExtractedData $extractedData, array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null)
            ? $payload['metadata']
            : [];

        if (($metadata['simulate_classification_failure'] ?? false) === true) {
            throw new RuntimeException('Simulated classification failure requested by payload metadata.');
        }

        $text = is_string($extractedData?->extracted_text)
            ? $extractedData->extracted_text
            : null;

        $type = $this->classificationRoutingResolver->resolveTypeHint(
            is_string($metadata['classification_hint'] ?? null) ? $metadata['classification_hint'] : null,
            $document->file_name,
            $text,
        );

        $confidence = match ($type) {
            'contract' => 0.9400,
            'tax' => 0.9200,
            'invoice' => 0.9100,
            default => 0.8600,
        };

        return [
            'provider' => 'simulated',
            'type' => $type,
            'confidence' => $confidence,
            'metadata' => [
                'simulated' => true,
                'routing_key' => $this->classificationRoutingResolver->resolveRoutingKeyForType($type),
            ],
        ];
    }
}
