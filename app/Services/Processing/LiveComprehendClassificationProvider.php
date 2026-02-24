<?php

namespace App\Services\Processing;

use App\Models\Document;
use App\Models\ExtractedData;
use Aws\Exception\AwsException;
use Aws\Laravel\AwsFacade as Aws;
use Aws\Result;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Throwable;

class LiveComprehendClassificationProvider implements ClassificationProvider
{
    public function __construct(
        public ClassificationRoutingResolver $classificationRoutingResolver,
        public ProviderCircuitBreaker $providerCircuitBreaker,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     provider: string,
     *     type: string,
     *     confidence: float|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function classify(Document $document, ?ExtractedData $extractedData, array $payload): array
    {
        $classificationText = $this->resolveClassificationText($document, $extractedData);
        $endpointArn = $this->resolveEndpointArn();

        $classificationResult = $this->providerCircuitBreaker->run(
            provider: 'comprehend',
            operation: function () use ($classificationText, $endpointArn): array {
                $comprehendClient = Aws::createClient('comprehend');

                /** @var Result $response */
                $response = $comprehendClient->classifyDocument([
                    'Text' => $classificationText,
                    'EndpointArn' => $endpointArn,
                ]);

                return $response->toArray();
            },
            isTransient: fn (Throwable $throwable): bool => $this->isTransientFailure($throwable),
        );

        $classes = is_array($classificationResult['Classes'] ?? null)
            ? $classificationResult['Classes']
            : [];

        $topClass = $this->resolveTopClass($classes);
        $topLabel = is_string($topClass['Name'] ?? null)
            ? trim($topClass['Name'])
            : null;
        $topScore = isset($topClass['Score']) && is_numeric($topClass['Score'])
            ? (float) $topClass['Score']
            : null;

        $normalizedTopLabel = is_string($topLabel) ? strtolower($topLabel) : null;
        $resolvedType = $this->classificationRoutingResolver->resolveTypeHint(
            $normalizedTopLabel,
            $document->file_name,
            $classificationText,
        );

        return [
            'provider' => 'comprehend',
            'type' => $resolvedType,
            'confidence' => $topScore,
            'metadata' => [
                'endpoint_arn' => $endpointArn,
                'selected_label' => $topLabel,
                'selected_score' => $topScore,
                'classes' => $classes,
            ],
        ];
    }

    protected function resolveClassificationText(Document $document, ?ExtractedData $extractedData): string
    {
        $text = is_string($extractedData?->extracted_text)
            ? trim($extractedData->extracted_text)
            : '';

        if ($text !== '') {
            return $text;
        }

        return trim($document->title.' '.$document->file_name);
    }

    protected function resolveEndpointArn(): string
    {
        return trim((string) config('processing.comprehend.endpoint_arn', ''));
    }

    /**
     * @param  array<int, mixed>  $classes
     * @return array<string, mixed>
     */
    protected function resolveTopClass(array $classes): array
    {
        $topClass = [];
        $topScore = null;

        foreach ($classes as $class) {
            if (! is_array($class) || ! is_string($class['Name'] ?? null)) {
                continue;
            }

            $score = isset($class['Score']) && is_numeric($class['Score'])
                ? (float) $class['Score']
                : null;

            if ($topScore === null || ($score !== null && $score > $topScore)) {
                $topClass = $class;
                $topScore = $score;
            }
        }

        return $topClass;
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
            'resourceunavailableexception',
            'toomanyrequestsexception',
            'throttlingexception',
            'internalserverexception',
            'serviceunavailableexception',
            'requesttimeoutexception',
        ], true)) {
            return true;
        }

        $statusCode = $throwable->getStatusCode();

        return is_int($statusCode) && $statusCode >= 500;
    }
}
