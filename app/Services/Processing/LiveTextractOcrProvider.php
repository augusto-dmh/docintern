<?php

namespace App\Services\Processing;

use App\Models\Document;
use Aws\Exception\AwsException;
use Aws\Laravel\AwsFacade as Aws;
use Aws\Result;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class LiveTextractOcrProvider implements OcrProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
        public ProviderCircuitBreaker $providerCircuitBreaker,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     extracted_text: string|null,
     *     payload: array<string, mixed>,
     *     metadata: array<string, mixed>,
     *     classification_hint: string|null
     * }
     */
    public function extract(Document $document, array $payload): array
    {
        $textractResult = $this->providerCircuitBreaker->run(
            provider: 'textract',
            operation: function () use ($document): array {
                $textractClient = Aws::createClient('textract');

                /** @var Result $response */
                $response = $textractClient->detectDocumentText([
                    'Document' => [
                        'S3Object' => [
                            'Bucket' => $this->resolveBucket(),
                            'Name' => $document->file_path,
                        ],
                    ],
                ]);

                return $response->toArray();
            },
            isTransient: fn (Throwable $throwable): bool => $this->isTransientFailure($throwable),
        );

        $blocks = is_array($textractResult['Blocks'] ?? null)
            ? $textractResult['Blocks']
            : [];

        $lines = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (($block['BlockType'] ?? null) !== 'LINE') {
                continue;
            }

            if (! is_string($block['Text'] ?? null)) {
                continue;
            }

            $text = trim($block['Text']);

            if ($text === '') {
                continue;
            }

            $lines[] = $text;
        }

        $extractedText = trim(implode("\n", $lines));

        $classificationHint = $this->classificationRoutingResolver->resolveTypeHint(
            null,
            $document->file_name,
            $extractedText === '' ? null : $extractedText,
        );

        return [
            'provider' => 'textract',
            'extracted_text' => $extractedText === '' ? null : $extractedText,
            'payload' => [
                'lines' => $lines,
                'block_count' => count($blocks),
                'model_version' => is_string($textractResult['DetectDocumentTextModelVersion'] ?? null)
                    ? $textractResult['DetectDocumentTextModelVersion']
                    : null,
                'raw_blocks' => $blocks,
            ],
            'metadata' => [
                'source' => 'textract.detect_document_text',
                'classification_hint' => $classificationHint,
            ],
            'classification_hint' => $classificationHint,
        ];
    }

    protected function resolveBucket(): string
    {
        return (string) config('filesystems.disks.s3.bucket', '');
    }

    protected function isTransientFailure(Throwable $throwable): bool
    {
        if ($throwable instanceof ConnectException || $throwable instanceof RequestException) {
            return true;
        }

        if (! $throwable instanceof AwsException) {
            return false;
        }

        $errorCode = strtolower((string) $throwable->getAwsErrorCode());

        if (in_array($errorCode, [
            'throttlingexception',
            'provisionedthroughputexceededexception',
            'internalservererror',
            'internalserverexception',
            'serviceunavailableexception',
            'toomanyrequestsexception',
            'resourceunavailableexception',
            'requesttimeoutexception',
        ], true)) {
            return true;
        }

        $statusCode = $throwable->getStatusCode();

        return is_int($statusCode) && $statusCode >= 500;
    }
}
