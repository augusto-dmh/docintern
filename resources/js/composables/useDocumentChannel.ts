import { onBeforeUnmount } from 'vue';
import { getEcho } from '@/lib/echo';
import type {
    DocumentChannelSnapshot,
    DocumentClassification,
    DocumentStatus,
} from '@/types';

export type DocumentStatusUpdatedPayload = {
    document_id: number;
    tenant_id: string;
    status_from: DocumentStatus | null;
    status_to: DocumentStatus;
    event: string;
    trace_id: string;
    occurred_at: string;
    classification: DocumentClassification | null;
    document: DocumentChannelSnapshot | null;
};

type UseDocumentChannelOptions = {
    tenantId?: string | null;
    documentId?: number | null;
    onStatusUpdated: (payload: DocumentStatusUpdatedPayload) => void;
};

function isDocumentStatus(value: string): value is DocumentStatus {
    return [
        'uploaded',
        'scanning',
        'scan_passed',
        'scan_failed',
        'extracting',
        'extraction_failed',
        'classifying',
        'classification_failed',
        'ready_for_review',
        'reviewed',
        'approved',
    ].includes(value);
}

function normalizePayload(
    payload: unknown,
): DocumentStatusUpdatedPayload | null {
    if (typeof payload !== 'object' || payload === null) {
        return null;
    }

    const candidatePayload = payload as Record<string, unknown>;
    const statusTo = candidatePayload.status_to;
    const statusFrom = candidatePayload.status_from;
    const classification = candidatePayload.classification;
    const document = candidatePayload.document;

    if (
        typeof candidatePayload.document_id !== 'number' ||
        typeof candidatePayload.tenant_id !== 'string' ||
        typeof statusTo !== 'string' ||
        !isDocumentStatus(statusTo) ||
        typeof candidatePayload.event !== 'string' ||
        typeof candidatePayload.trace_id !== 'string' ||
        typeof candidatePayload.occurred_at !== 'string'
    ) {
        return null;
    }

    if (statusFrom !== null && typeof statusFrom !== 'string') {
        return null;
    }

    if (typeof statusFrom === 'string' && !isDocumentStatus(statusFrom)) {
        return null;
    }

    if (
        classification !== null &&
        classification !== undefined &&
        !(
            typeof classification === 'object' &&
            classification !== null &&
            typeof (classification as Record<string, unknown>).provider ===
                'string' &&
            typeof (classification as Record<string, unknown>).type ===
                'string' &&
            ((classification as Record<string, unknown>).confidence === null ||
                typeof (classification as Record<string, unknown>)
                    .confidence === 'number' ||
                typeof (classification as Record<string, unknown>)
                    .confidence === 'string')
        )
    ) {
        return null;
    }

    if (
        document !== null &&
        document !== undefined &&
        !(
            typeof document === 'object' &&
            document !== null &&
            typeof (document as Record<string, unknown>).title === 'string' &&
            ((document as Record<string, unknown>).matter_title === null ||
                typeof (document as Record<string, unknown>).matter_title ===
                    'string')
        )
    ) {
        return null;
    }

    return {
        document_id: candidatePayload.document_id,
        tenant_id: candidatePayload.tenant_id,
        status_from: statusFrom,
        status_to: statusTo,
        event: candidatePayload.event,
        trace_id: candidatePayload.trace_id,
        occurred_at: candidatePayload.occurred_at,
        classification:
            classification === undefined
                ? null
                : (classification as DocumentClassification | null),
        document:
            document === undefined
                ? null
                : (document as DocumentChannelSnapshot | null),
    };
}

export function useDocumentChannel(options: UseDocumentChannelOptions): void {
    const echo = getEcho();

    if (!echo) {
        return;
    }

    const subscribedChannels: string[] = [];
    const listenForUpdates = (channelName: string): void => {
        echo.private(channelName).listen(
            '.document.status.updated',
            (payload: unknown): void => {
                const normalizedPayload = normalizePayload(payload);

                if (!normalizedPayload) {
                    return;
                }

                options.onStatusUpdated(normalizedPayload);
            },
        );

        subscribedChannels.push(channelName);
    };

    if (options.tenantId) {
        listenForUpdates(`tenant.${options.tenantId}.documents`);
    }

    if (options.documentId) {
        listenForUpdates(`document.${options.documentId}`);
    }

    onBeforeUnmount((): void => {
        subscribedChannels.forEach((channelName) => {
            echo.leave(channelName);
        });
    });
}
