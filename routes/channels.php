<?php

use App\Models\User;
use App\Support\BroadcastChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;

$broadcastChannelAuthorizer = app(BroadcastChannelAuthorizer::class);

Broadcast::channel('tenant.{tenantId}.documents', function (User $user, string $tenantId) use ($broadcastChannelAuthorizer): bool {
    return $broadcastChannelAuthorizer->canAccessTenantDocumentsChannel($user, $tenantId);
});

Broadcast::channel('document.{documentId}', function (User $user, int $documentId) use ($broadcastChannelAuthorizer): bool {
    return $broadcastChannelAuthorizer->canAccessDocumentChannel($user, $documentId);
});
