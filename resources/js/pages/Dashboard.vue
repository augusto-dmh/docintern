<script setup lang="ts">
import { dashboard } from '@/routes';
import { index as clientsIndex } from '@/routes/clients';
import { index as documentsIndex } from '@/routes/documents';
import { index as mattersIndex } from '@/routes/matters';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, Briefcase, FileText, Users } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import ProcessingPipeline from '@/components/dashboard/ProcessingPipeline.vue';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import { useEchoConnectionStatus } from '@/composables/useEchoConnectionStatus';
import AppLayout from '@/layouts/AppLayout.vue';
import { isFailureDocumentStatus } from '@/lib/document-pipeline';
import {
    type BreadcrumbItem,
    type DashboardPipelineDocument,
    type DashboardStats,
    type DocumentStatus,
} from '@/types';

const props = defineProps<{
    realtimeTenantId: string | null;
    pipelineDocuments: DashboardPipelineDocument[];
    stats: DashboardStats;
}>();

const page = usePage();
const tenant = page.props.tenant;
const livePipelineDocuments = ref<DashboardPipelineDocument[]>([
    ...props.pipelineDocuments,
]);
const liveStats = ref<DashboardStats>({ ...props.stats });
const connectionStatus = useEchoConnectionStatus();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const pendingReviewStatuses = new Set<DocumentStatus>([
    'ready_for_review',
    'reviewed',
]);

const quickLinks = [
    {
        title: 'Clients',
        description: 'Manage contacts and engagement records.',
        href: clientsIndex(),
        icon: Users,
    },
    {
        title: 'Matters',
        description: 'Track case progress, ownership, and status.',
        href: mattersIndex(),
        icon: Briefcase,
    },
    {
        title: 'Documents',
        description: 'Review uploads, approvals, and audit activity.',
        href: documentsIndex(),
        icon: FileText,
    },
];

watch(
    () => props.pipelineDocuments,
    (documents) => {
        livePipelineDocuments.value = [...documents];
    },
);

watch(
    () => props.stats,
    (stats) => {
        liveStats.value = { ...stats };
    },
    { deep: true },
);

function isPendingReviewStatus(status: DocumentStatus): boolean {
    return pendingReviewStatuses.has(status);
}

function isFailedStatus(status: DocumentStatus): boolean {
    return isFailureDocumentStatus(status);
}

function applyStatsDelta(
    fromStatus: DocumentStatus | null,
    toStatus: DocumentStatus,
): void {
    if (fromStatus !== 'approved' && toStatus === 'approved') {
        liveStats.value.processed_today += 1;
    }

    if (
        fromStatus &&
        isPendingReviewStatus(fromStatus) &&
        !isPendingReviewStatus(toStatus) &&
        liveStats.value.pending_review > 0
    ) {
        liveStats.value.pending_review -= 1;
    }

    if (
        !fromStatus ||
        (!isPendingReviewStatus(fromStatus) && isPendingReviewStatus(toStatus))
    ) {
        liveStats.value.pending_review += 1;
    }

    if (
        fromStatus &&
        isFailedStatus(fromStatus) &&
        !isFailedStatus(toStatus) &&
        liveStats.value.failed > 0
    ) {
        liveStats.value.failed -= 1;
    }

    if (
        !fromStatus ||
        (!isFailedStatus(fromStatus) && isFailedStatus(toStatus))
    ) {
        liveStats.value.failed += 1;
    }
}

if (props.realtimeTenantId) {
    useDocumentChannel({
        tenantId: props.realtimeTenantId,
        onStatusUpdated: (payload) => {
            const existingDocumentIndex = livePipelineDocuments.value.findIndex(
                (document) => document.id === payload.document_id,
            );
            const previousStatus =
                existingDocumentIndex >= 0
                    ? livePipelineDocuments.value[existingDocumentIndex].status
                    : null;

            if (existingDocumentIndex >= 0) {
                livePipelineDocuments.value[existingDocumentIndex] = {
                    ...livePipelineDocuments.value[existingDocumentIndex],
                    status: payload.status_to,
                    updated_at: payload.occurred_at,
                };
            } else {
                livePipelineDocuments.value.unshift({
                    id: payload.document_id,
                    title: `Document #${payload.document_id}`,
                    status: payload.status_to,
                    matter_title: null,
                    updated_at: payload.occurred_at,
                });
            }

            livePipelineDocuments.value = livePipelineDocuments.value
                .sort(
                    (firstDocument, secondDocument) =>
                        new Date(secondDocument.updated_at).getTime() -
                        new Date(firstDocument.updated_at).getTime(),
                )
                .slice(0, 8);

            applyStatsDelta(previousStatus, payload.status_to);
        },
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <section class="workspace-hero p-6 sm:p-8">
            <p
                class="doc-seal text-xs font-semibold tracking-[0.16em] uppercase"
            >
                Operations center
            </p>
            <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                {{ tenant?.name ?? 'Docintern workspace' }}
            </h1>
            <p class="doc-subtle mt-3 max-w-3xl text-sm sm:text-base">
                Run client, matter, and document workflows from one secure
                tenant-scoped surface.
            </p>
        </section>

        <div class="mt-6 grid gap-4 md:grid-cols-3">
            <article
                v-for="item in quickLinks"
                :key="item.title"
                class="workspace-panel workspace-fade-up p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="doc-title text-xl font-semibold">
                            {{ item.title }}
                        </p>
                        <p class="doc-subtle mt-2 text-sm">
                            {{ item.description }}
                        </p>
                    </div>
                    <component
                        :is="item.icon"
                        class="size-5 text-[var(--doc-seal)]"
                    />
                </div>

                <Link
                    :href="item.href"
                    class="doc-seal mt-5 inline-flex items-center gap-2 text-xs font-semibold tracking-[0.12em] uppercase hover:underline"
                >
                    Open
                    <ArrowRight class="size-4" />
                </Link>
            </article>
        </div>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-1 mt-6 p-6 sm:p-8"
        >
            <div>
                <div>
                    <h2 class="doc-title text-2xl font-semibold">
                        Live processing snapshot
                    </h2>
                    <p class="doc-subtle mt-2 text-sm">
                        Realtime status updates from tenant-scoped processing
                        channels.
                    </p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                <div class="workspace-kpi">
                    <p class="workspace-label">Processed today</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ liveStats.processed_today }}
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-label">Pending review</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ liveStats.pending_review }}
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-label">Failed documents</p>
                    <p class="workspace-kpi-value mt-2">
                        {{ liveStats.failed }}
                    </p>
                </div>
            </div>

            <div class="mt-5 space-y-3">
                <ProcessingPipeline
                    :documents="livePipelineDocuments"
                    :connection-status="connectionStatus"
                    :realtime-enabled="Boolean(realtimeTenantId)"
                />
            </div>
        </section>

        <section
            class="workspace-panel workspace-fade-up workspace-delay-2 mt-6 p-6 sm:p-8"
        >
            <h2 class="doc-title text-2xl font-semibold">Workflow posture</h2>
            <p class="doc-subtle mt-3 text-sm">
                Keep intake quality high by reviewing new uploads daily,
                promoting approved documents, and validating tenant context
                before cross-workspace work.
            </p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Client intake</p>
                    <p class="workspace-kpi-label">
                        Maintain complete contact data before opening matters.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Matter hygiene</p>
                    <p class="workspace-kpi-label">
                        Keep status and reference numbers current.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Document review</p>
                    <p class="workspace-kpi-label">
                        Move files from uploaded to approved quickly.
                    </p>
                </div>
                <div class="workspace-kpi">
                    <p class="workspace-kpi-value">Audit readiness</p>
                    <p class="workspace-kpi-label">
                        Verify timeline entries for critical files.
                    </p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
