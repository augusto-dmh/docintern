<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import DocumentStatusBadge from '@/components/documents/DocumentStatusBadge.vue';
import ExtractedDataPanel from '@/components/documents/ExtractedDataPanel.vue';
import PdfViewer from '@/components/documents/PdfViewer.vue';
import { Button } from '@/components/ui/button';
import UploadProgressTracker from '@/components/UploadProgressTracker.vue';
import { useDocumentChannel } from '@/composables/useDocumentChannel';
import { useEchoConnectionStatus } from '@/composables/useEchoConnectionStatus';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Document,
    type DocumentActivity,
    type DocumentExperienceGuardrails,
    type DocumentReviewWorkspace,
} from '@/types';

const props = defineProps<{
    document: Document;
    recentActivity: DocumentActivity[];
    reviewWorkspace: DocumentReviewWorkspace;
    documentExperience: DocumentExperienceGuardrails;
}>();

const permissions = usePage().props.auth.permissions;
const canEditDocuments = permissions.includes('edit documents');
const canApproveDocuments = permissions.includes('approve documents');
const liveStatus = ref(props.document.status);
const liveClassification = ref(props.document.classification);
const liveExtractedData = ref(props.document.extracted_data ?? null);
const liveUpdatedAt = ref(props.document.updated_at);
const refreshingDocumentSnapshot = ref(false);
const reviewForm = useForm({});
const approveForm = useForm({});
const connectionStatus = useEchoConnectionStatus();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
    },
];

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(new Date(value));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function formatFileSize(bytes: number): string {
    const sizeInMb = bytes / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function activityLabel(action: string): string {
    if (action === 'uploaded') {
        return 'Document uploaded';
    }

    if (action === 'viewed') {
        return 'Document viewed';
    }

    if (action === 'downloaded') {
        return 'Document downloaded';
    }

    if (action === 'deleted') {
        return 'Document deleted';
    }

    if (action === 'reviewed') {
        return 'Document reviewed';
    }

    if (action === 'approved') {
        return 'Document approved';
    }

    return action.replaceAll('_', ' ');
}

function canMarkReviewed(): boolean {
    return canApproveDocuments && liveStatus.value === 'ready_for_review';
}

function canApproveDocument(): boolean {
    return canApproveDocuments && liveStatus.value === 'reviewed';
}

function shouldRefreshDocumentSnapshotForClassification(
    status: string,
): boolean {
    return ['ready_for_review', 'reviewed', 'approved'].includes(status);
}

function refreshDocumentSnapshot(): void {
    if (refreshingDocumentSnapshot.value) {
        return;
    }

    refreshingDocumentSnapshot.value = true;

    router.reload({
        only: ['document'],
        preserveScroll: true,
        onFinish: () => {
            refreshingDocumentSnapshot.value = false;
        },
    });
}

function markReviewed(): void {
    reviewForm.submit(DocumentController.review(props.document), {
        preserveScroll: true,
    });
}

function approveDocument(): void {
    approveForm.submit(DocumentController.approve(props.document), {
        preserveScroll: true,
    });
}

watch(
    () => props.document.status,
    (status) => {
        liveStatus.value = status;
    },
);

watch(
    () => props.document.classification,
    (classification) => {
        liveClassification.value = classification;
    },
);

watch(
    () => props.document.extracted_data,
    (extractedData) => {
        liveExtractedData.value = extractedData ?? null;
    },
);

watch(
    () => props.document.updated_at,
    (updatedAt) => {
        liveUpdatedAt.value = updatedAt;
    },
);

useDocumentChannel({
    tenantId: props.document.tenant_id,
    documentId: props.document.id,
    onStatusUpdated: (payload) => {
        if (payload.document_id !== props.document.id) {
            return;
        }

        liveStatus.value = payload.status_to;
        liveUpdatedAt.value = payload.occurred_at;

        if (payload.classification !== null) {
            liveClassification.value = payload.classification;
        }

        if (
            (liveClassification.value === null ||
                liveExtractedData.value === null) &&
            shouldRefreshDocumentSnapshotForClassification(payload.status_to)
        ) {
            refreshDocumentSnapshot();
        }
    },
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="document.title" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Case document"
            :title="document.title"
        >
            <template #description>
                <span class="inline-flex items-center gap-2">
                    <span class="doc-subtle text-sm">Current status</span>
                    <DocumentStatusBadge :status="liveStatus" />
                </span>
            </template>

            <template #actions>
                <Button
                    v-if="canMarkReviewed()"
                    variant="outline"
                    :disabled="reviewForm.processing"
                    @click="markReviewed"
                >
                    {{ reviewForm.processing ? 'Marking...' : 'Mark Reviewed' }}
                </Button>

                <Button
                    v-if="canApproveDocument()"
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                    :disabled="approveForm.processing"
                    @click="approveDocument"
                >
                    {{
                        approveForm.processing
                            ? 'Approving...'
                            : 'Approve Document'
                    }}
                </Button>

                <Button
                    as-child
                    class="bg-[var(--doc-seal)] text-white hover:bg-primary/90"
                >
                    <Link :href="DocumentController.download(document)">
                        Download
                    </Link>
                </Button>

                <Button v-if="canEditDocuments" as-child variant="outline">
                    <Link :href="DocumentController.edit(document)">
                        Edit metadata
                    </Link>
                </Button>
            </template>

            <p
                v-if="reviewForm.errors.status || approveForm.errors.status"
                class="mt-4 text-sm text-destructive"
            >
                {{ reviewForm.errors.status ?? approveForm.errors.status }}
            </p>

            <UploadProgressTracker
                :document-experience="documentExperience"
                :items="[]"
                :document-status="liveStatus"
                :connection-status="connectionStatus"
                :updated-at="liveUpdatedAt"
                class="mt-6"
            />

            <div
                class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(22rem,0.95fr)]"
            >
                <PdfViewer
                    v-if="
                        reviewWorkspace.preview.available &&
                        reviewWorkspace.preview.url
                    "
                    :document-experience="documentExperience"
                    :src="reviewWorkspace.preview.url"
                    :title="document.file_name"
                    :delay="1"
                />

                <DocumentExperienceSurface
                    v-else
                    :document-experience="documentExperience"
                    :delay="1"
                    class="p-6 sm:p-8"
                >
                    <p
                        class="doc-subtle text-xs font-semibold tracking-[0.14em] uppercase"
                    >
                        Preview unavailable
                    </p>
                    <h2 class="doc-title mt-2 text-2xl font-semibold">
                        This file stays download-first for now
                    </h2>
                    <p class="doc-subtle mt-3 max-w-2xl text-sm leading-6">
                        Phase 5 R1 supports inline review for PDFs only. This
                        document can still be downloaded securely for manual
                        inspection.
                    </p>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(34_32%_97%/0.88)] p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                File name
                            </dt>
                            <dd class="doc-title mt-2 text-base font-semibold">
                                {{ document.file_name }}
                            </dd>
                        </div>
                        <div
                            class="rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(34_32%_97%/0.88)] p-4"
                        >
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                MIME type
                            </dt>
                            <dd class="mt-2 text-sm">
                                {{
                                    reviewWorkspace.preview.mime_type ??
                                    document.mime_type ??
                                    'Unknown'
                                }}
                            </dd>
                        </div>
                    </dl>
                </DocumentExperienceSurface>

                <ExtractedDataPanel
                    :document-experience="documentExperience"
                    :classification="liveClassification ?? null"
                    :extracted-data="liveExtractedData"
                    :delay="1"
                />
            </div>

            <div
                class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(20rem,0.85fr)_minmax(0,1.15fr)]"
            >
                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="2"
                    class="p-6 sm:p-7"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <p
                                class="doc-subtle text-xs font-semibold tracking-[0.14em] uppercase"
                            >
                                Document record
                            </p>
                            <h2 class="doc-title mt-1 text-xl font-semibold">
                                Metadata and routing
                            </h2>
                        </div>

                        <DocumentStatusBadge :status="liveStatus" />
                    </div>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                File size
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ formatFileSize(document.file_size) }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Uploaded by
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ document.uploader?.name ?? 'System' }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Matter
                            </dt>
                            <dd class="mt-1 text-sm">
                                <Link
                                    v-if="document.matter"
                                    :href="
                                        MatterController.show(document.matter)
                                    "
                                    class="doc-seal hover:underline"
                                >
                                    {{ document.matter.title }}
                                </Link>
                                <span v-else>—</span>
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Recorded on
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ formatDate(document.created_at) }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Last updated
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ formatDate(liveUpdatedAt) }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                MIME type
                            </dt>
                            <dd class="mt-1 text-sm">
                                {{ document.mime_type ?? 'Unknown' }}
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt
                                class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                            >
                                Storage key
                            </dt>
                            <dd class="mt-1 text-xs break-all">
                                {{ document.file_path }}
                            </dd>
                        </div>
                    </dl>
                </DocumentExperienceSurface>

                <DocumentExperienceSurface
                    :document-experience="documentExperience"
                    :delay="2"
                    class="p-6 sm:p-7"
                >
                    <div
                        class="mb-4 flex flex-wrap items-center justify-between gap-2"
                    >
                        <h2 class="doc-title text-xl font-semibold">
                            Activity timeline
                        </h2>
                        <span
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            {{ recentActivity.length }} events
                        </span>
                    </div>

                    <ol class="space-y-3">
                        <li
                            v-for="activity in recentActivity"
                            :key="activity.id"
                            class="doc-grid-line rounded-xl border p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="doc-title text-sm font-semibold">
                                    {{ activityLabel(activity.action) }}
                                </p>
                                <p class="doc-subtle text-xs">
                                    {{ formatDateTime(activity.created_at) }}
                                </p>
                            </div>

                            <p class="doc-subtle mt-1 text-xs">
                                {{ activity.user?.name ?? 'System' }}
                                <span v-if="activity.ip_address">
                                    • {{ activity.ip_address }}
                                </span>
                            </p>
                        </li>
                    </ol>
                </DocumentExperienceSurface>
            </div>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
