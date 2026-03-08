<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, BellRing, CircleCheck, TriangleAlert, X } from 'lucide-vue-next';
import { computed } from 'vue';
import { show } from '@/actions/App/Http/Controllers/DocumentController';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import {
    dismissRealtimeNotification,
    publishRealtimeNotification,
    useRealtimeNotifications,
    type WorkspaceNotification,
} from '@/lib/realtime-notifications';

const page = usePage();
const notifications = useRealtimeNotifications();
const tenantId = computed(() => page.props.tenant?.id ?? null);

function notificationToneClass(notification: WorkspaceNotification): string {
    if (notification.tone === 'failure') {
        return 'workspace-notice-card--failure';
    }

    if (notification.tone === 'success') {
        return 'workspace-notice-card--success';
    }

    return 'workspace-notice-card--info';
}

function notificationIcon(notification: WorkspaceNotification) {
    if (notification.tone === 'failure') {
        return TriangleAlert;
    }

    if (notification.tone === 'success') {
        return CircleCheck;
    }

    return BellRing;
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

useDocumentChannel({
    tenantId: tenantId.value,
    onStatusUpdated: (payload) => {
        publishRealtimeNotification(payload);
    },
});
</script>

<template>
    <div
        v-if="notifications.length > 0"
        class="pointer-events-none fixed inset-x-4 top-4 z-50 sm:inset-x-auto sm:top-5 sm:right-5 sm:w-[min(26rem,calc(100vw-2rem))]"
    >
        <div class="workspace-notice-rail space-y-3">
            <article
                v-for="notification in notifications"
                :key="notification.id"
                class="workspace-notice-card pointer-events-auto"
                :class="notificationToneClass(notification)"
            >
                <div class="flex items-start gap-3">
                    <div class="workspace-notice-icon">
                        <component
                            :is="notificationIcon(notification)"
                            class="size-4"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="doc-title text-sm font-semibold">
                                    {{ notification.title }}
                                </p>
                                <p class="doc-subtle mt-1 text-sm">
                                    {{ notification.message }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="workspace-notice-dismiss"
                                @click="
                                    dismissRealtimeNotification(
                                        notification.id,
                                    )
                                "
                            >
                                <span class="sr-only">Dismiss notification</span>
                                <X class="size-4" />
                            </button>
                        </div>

                        <div
                            class="mt-4 flex flex-wrap items-center justify-between gap-3"
                        >
                            <span class="doc-subtle text-[0.72rem] uppercase">
                                {{ formatDateTime(notification.occurredAt) }}
                            </span>

                            <Link
                                :href="show(notification.documentId).url"
                                class="doc-seal inline-flex items-center gap-1.5 text-[0.72rem] font-semibold tracking-[0.12em] uppercase hover:underline"
                            >
                                Open document
                                <ArrowUpRight class="size-3.5" />
                            </Link>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>
