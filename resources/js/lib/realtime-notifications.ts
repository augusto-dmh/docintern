import { readonly, ref } from 'vue';
import type { DocumentStatusUpdatedPayload } from '@/composables/useDocumentChannel';

export type WorkspaceNotificationTone = 'info' | 'success' | 'failure';

export type WorkspaceNotification = {
    id: string;
    dedupeKey: string;
    documentId: number;
    title: string;
    message: string;
    tone: WorkspaceNotificationTone;
    occurredAt: string;
};

const visibleStatuses = new Set([
    'uploaded',
    'ready_for_review',
    'reviewed',
    'approved',
    'scan_failed',
    'extraction_failed',
    'classification_failed',
]);

const failureStatuses = new Set([
    'scan_failed',
    'extraction_failed',
    'classification_failed',
]);

const notifications = ref<WorkspaceNotification[]>([]);
const dismissalTimers = new Map<string, ReturnType<typeof setTimeout>>();

function documentLabel(payload: DocumentStatusUpdatedPayload): string | null {
    if (payload.document === null) {
        return null;
    }

    return payload.document.matter_title
        ? `${payload.document.title} · ${payload.document.matter_title}`
        : payload.document.title;
}

function failureStageLabel(
    status: DocumentStatusUpdatedPayload['status_to'],
): string {
    if (status === 'scan_failed') {
        return 'virus scan';
    }

    if (status === 'extraction_failed') {
        return 'extraction';
    }

    return 'classification';
}

function buildNotification(
    payload: DocumentStatusUpdatedPayload,
): WorkspaceNotification | null {
    if (!visibleStatuses.has(payload.status_to)) {
        return null;
    }

    const label = documentLabel(payload);

    if (label === null) {
        return null;
    }

    if (failureStatuses.has(payload.status_to)) {
        return {
            id: `${payload.document_id}:${payload.status_to}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.status_to}`,
            documentId: payload.document_id,
            title: `Attention required · ${label}`,
            message: `The document stopped during ${failureStageLabel(payload.status_to)} and needs review.`,
            tone: 'failure',
            occurredAt: payload.occurred_at,
        };
    }

    if (payload.status_to === 'uploaded') {
        return {
            id: `${payload.document_id}:${payload.status_to}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.status_to}`,
            documentId: payload.document_id,
            title: `Upload received · ${label}`,
            message: 'The document entered the tenant processing queue.',
            tone: 'info',
            occurredAt: payload.occurred_at,
        };
    }

    if (payload.status_to === 'ready_for_review') {
        return {
            id: `${payload.document_id}:${payload.status_to}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.status_to}`,
            documentId: payload.document_id,
            title: `Ready for review · ${label}`,
            message:
                'Extraction and classification completed. A reviewer can inspect the result now.',
            tone: 'success',
            occurredAt: payload.occurred_at,
        };
    }

    if (payload.status_to === 'reviewed') {
        return {
            id: `${payload.document_id}:${payload.status_to}:${payload.occurred_at}`,
            dedupeKey: `${payload.document_id}:${payload.status_to}`,
            documentId: payload.document_id,
            title: `Review logged · ${label}`,
            message:
                'The document was marked as reviewed and is ready for approval.',
            tone: 'success',
            occurredAt: payload.occurred_at,
        };
    }

    return {
        id: `${payload.document_id}:${payload.status_to}:${payload.occurred_at}`,
        dedupeKey: `${payload.document_id}:${payload.status_to}`,
        documentId: payload.document_id,
        title: `Approved · ${label}`,
        message: 'The document completed the review cycle and is now approved.',
        tone: 'success',
        occurredAt: payload.occurred_at,
    };
}

function clearDismissalTimer(id: string): void {
    const timer = dismissalTimers.get(id);

    if (timer) {
        clearTimeout(timer);
        dismissalTimers.delete(id);
    }
}

export function dismissRealtimeNotification(id: string): void {
    clearDismissalTimer(id);
    notifications.value = notifications.value.filter(
        (notification) => notification.id !== id,
    );
}

export function publishRealtimeNotification(
    payload: DocumentStatusUpdatedPayload,
): void {
    const notification = buildNotification(payload);

    if (notification === null) {
        return;
    }

    if (notifications.value.some((item) => item.id === notification.id)) {
        return;
    }

    const existingIndex = notifications.value.findIndex(
        (item) => item.dedupeKey === notification.dedupeKey,
    );

    if (existingIndex >= 0) {
        const existing = notifications.value[existingIndex];

        if (
            new Date(existing.occurredAt).getTime() >=
            new Date(notification.occurredAt).getTime()
        ) {
            return;
        }

        clearDismissalTimer(existing.id);
        notifications.value.splice(existingIndex, 1);
    }

    const nextNotifications = [notification, ...notifications.value];
    const removedNotifications = nextNotifications.slice(5);

    removedNotifications.forEach((removedNotification) => {
        clearDismissalTimer(removedNotification.id);
    });

    notifications.value = nextNotifications.slice(0, 5);

    if (notification.tone !== 'failure') {
        dismissalTimers.set(
            notification.id,
            setTimeout(() => {
                dismissRealtimeNotification(notification.id);
            }, 6000),
        );
    }
}

export function useRealtimeNotifications() {
    return readonly(notifications);
}
