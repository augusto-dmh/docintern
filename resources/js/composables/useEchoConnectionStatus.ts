import { onBeforeUnmount, ref, type Ref } from 'vue';
import { type RealtimeConnectionStatus } from '@/lib/document-pipeline';
import { getEcho } from '@/lib/echo';

function normalizeConnectionStatus(status: string): RealtimeConnectionStatus {
    if (
        status === 'connecting' ||
        status === 'connected' ||
        status === 'reconnecting' ||
        status === 'disconnected' ||
        status === 'failed'
    ) {
        return status;
    }

    return 'disconnected';
}

export function useEchoConnectionStatus(): Ref<RealtimeConnectionStatus> {
    const echo = getEcho();

    if (!echo) {
        return ref('disabled');
    }

    const connectionStatus = ref<RealtimeConnectionStatus>(
        normalizeConnectionStatus(echo.connectionStatus()),
    );

    const unsubscribe = echo.connector.onConnectionChange((status): void => {
        connectionStatus.value = normalizeConnectionStatus(status);
    });

    onBeforeUnmount((): void => {
        unsubscribe();
    });

    return connectionStatus;
}
