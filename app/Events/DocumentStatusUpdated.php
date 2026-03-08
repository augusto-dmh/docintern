<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class DocumentStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  array{provider: string, type: string, confidence: float|string|null}|null  $classification
     */
    public function __construct(
        public int $documentId,
        public string $tenantId,
        public ?string $statusFrom,
        public string $statusTo,
        public string $event,
        public string $traceId,
        public CarbonImmutable $occurredAt,
        public ?array $classification = null,
    ) {}

    public function broadcastAs(): string
    {
        return 'document.status.updated';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.documents'),
            new PrivateChannel('document.'.$this->documentId),
        ];
    }

    /**
     * @return array{
     *     document_id: int,
     *     tenant_id: string,
     *     status_from: string|null,
     *     status_to: string,
     *     event: string,
     *     trace_id: string,
     *     occurred_at: string,
     *     classification: array{provider: string, type: string, confidence: float|string|null}|null
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'document_id' => $this->documentId,
            'tenant_id' => $this->tenantId,
            'status_from' => $this->statusFrom,
            'status_to' => $this->statusTo,
            'event' => $this->event,
            'trace_id' => $this->traceId,
            'occurred_at' => $this->occurredAt->toISOString(),
            'classification' => $this->classification,
        ];
    }
}
