<script setup lang="ts">
import { computed } from 'vue';
import {
    documentStatusToneClass,
    documentSurfaceClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import {
    type RealtimeConnectionStatus,
    isFailureDocumentStatus,
    isTerminalDocumentStatus,
    pipelineStageForStatus,
    pipelineStageIndex,
    pipelineStages,
} from '@/lib/document-pipeline';
import type { DocumentExperienceGuardrails, DocumentStatus } from '@/types';

export type UploadStatus = 'uploading' | 'completed' | 'failed';

export type UploadProgressItem = {
    name: string;
    size: number;
    progress: number;
    status: UploadStatus;
};

type StagePresentation = {
    id: string;
    label: string;
    description: string;
    state: 'complete' | 'active' | 'failed' | 'upcoming';
};

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    items: UploadProgressItem[];
    documentStatus?: DocumentStatus | null;
    connectionStatus?: RealtimeConnectionStatus;
    updatedAt?: string | null;
};

const props = withDefaults(defineProps<Props>(), {
    documentStatus: null,
    connectionStatus: 'disabled',
    updatedAt: null,
});

const trackerClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'space-y-4 p-4',
    ),
);

const trackerTitleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-sm font-semibold',
    ),
);

const processingStages = computed<StagePresentation[]>(() => {
    if (!props.documentStatus) {
        return [];
    }

    const activeStageIndex = pipelineStageIndex(props.documentStatus);
    const currentStage = pipelineStageForStatus(props.documentStatus);
    const failed = isFailureDocumentStatus(props.documentStatus);
    const terminal = isTerminalDocumentStatus(props.documentStatus);

    return pipelineStages.map((stage, index) => {
        if (failed && stage.id === currentStage) {
            return { ...stage, state: 'failed' };
        }

        if (index < activeStageIndex) {
            return { ...stage, state: 'complete' };
        }

        if (index === activeStageIndex) {
            if (terminal && props.documentStatus === 'approved') {
                return { ...stage, state: 'complete' };
            }

            return { ...stage, state: 'active' };
        }

        return { ...stage, state: 'upcoming' };
    });
});

const connectionCopy = computed(() => {
    if (!props.documentStatus) {
        return null;
    }

    if (props.connectionStatus === 'connecting') {
        return {
            label: 'Connecting',
            tone: 'workspace-status-pill--success',
            description: 'Opening live processing updates for this document.',
        };
    }

    if (props.connectionStatus === 'connected') {
        return {
            label: 'Live',
            tone: 'workspace-status-pill--success',
            description: 'Stage changes will settle here as broadcasts arrive.',
        };
    }

    if (props.connectionStatus === 'reconnecting') {
        return {
            label: 'Reconnecting',
            tone: 'workspace-status-pill--warning',
            description:
                'Keeping the last known processing position while the stream resumes.',
        };
    }

    if (
        props.connectionStatus === 'disconnected' ||
        props.connectionStatus === 'failed'
    ) {
        return {
            label:
                props.connectionStatus === 'failed'
                    ? 'Connection failed'
                    : 'Offline',
            tone: 'workspace-status-pill--warning',
            description:
                'Realtime delivery is unavailable. The current stage remains visible.',
        };
    }

    return {
        label: 'Realtime unavailable',
        tone: 'workspace-status-pill--warning',
        description: 'The tracker is showing server-rendered status only.',
    };
});

const processingStatusLabel = computed(
    () => props.documentStatus?.replaceAll('_', ' ') ?? null,
);

function formatSize(size: number): string {
    const sizeInMb = size / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(size / 1024))} KB`;
}

function formatUpdatedAt(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

function statusLabel(status: UploadStatus): string {
    if (status === 'completed') {
        return 'Completed';
    }

    if (status === 'failed') {
        return 'Failed';
    }

    return 'Uploading';
}

function statusClass(status: UploadStatus): string {
    return documentStatusToneClass(status);
}

function stageStateClass(state: StagePresentation['state']): string {
    if (state === 'complete') {
        return 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    }

    if (state === 'active') {
        return 'border-[var(--doc-seal)]/35 bg-[var(--doc-seal)]/12 text-[var(--doc-seal)]';
    }

    if (state === 'failed') {
        return 'border-red-200 bg-red-100 text-red-700 dark:border-red-900 dark:bg-red-900/40 dark:text-red-300';
    }

    return 'border-[var(--doc-border)]/80 bg-[hsl(39_42%_95%/0.85)] text-[hsl(26_14%_38%)]';
}

function stageDotClass(state: StagePresentation['state']): string {
    if (state === 'complete') {
        return 'border-emerald-200 bg-emerald-600 shadow-[0_0_0_5px_hsl(142_76%_90%/0.5)]';
    }

    if (state === 'active') {
        return 'border-[var(--doc-seal)]/20 bg-[var(--doc-seal)] shadow-[0_0_0_5px_hsl(9_72%_34%/0.12)]';
    }

    if (state === 'failed') {
        return 'border-red-200 bg-red-600 shadow-[0_0_0_5px_hsl(0_84%_90%/0.55)]';
    }

    return 'border-[var(--doc-border)]/80 bg-[hsl(38_18%_76%)]';
}
</script>

<template>
    <div v-if="items.length || documentStatus" :class="trackerClass">
        <section v-if="items.length" class="space-y-3">
            <h3 :class="trackerTitleClass">Transfer progress</h3>

            <ul class="space-y-3">
                <li
                    v-for="item in items"
                    :key="item.name"
                    class="space-y-2 rounded-lg border border-[var(--doc-border)]/80 p-3"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div>
                            <p class="doc-title text-sm font-semibold">
                                {{ item.name }}
                            </p>
                            <p class="doc-subtle text-xs">
                                {{ formatSize(item.size) }}
                            </p>
                        </div>

                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="statusClass(item.status)"
                        >
                            {{ statusLabel(item.status) }}
                        </span>
                    </div>

                    <div
                        class="h-1.5 overflow-hidden rounded-full bg-[var(--doc-paper-strong)]"
                    >
                        <div
                            class="h-full rounded-full bg-[var(--doc-seal)] transition-all duration-300"
                            :style="{ width: `${item.progress}%` }"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="documentStatus" class="space-y-4">
            <div
                class="flex flex-col gap-3 rounded-xl border border-[var(--doc-border)]/75 bg-[hsl(39_58%_98%/0.9)] p-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h3 :class="trackerTitleClass">Processing progression</h3>
                    <p class="doc-subtle mt-1 text-sm">
                        {{ connectionCopy?.description }}
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span
                            v-if="processingStatusLabel"
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="documentStatusToneClass(documentStatus)"
                        >
                            {{ processingStatusLabel }}
                        </span>
                        <span v-if="updatedAt" class="doc-subtle text-xs">
                            Updated {{ formatUpdatedAt(updatedAt) }}
                        </span>
                    </div>
                </div>

                <span
                    v-if="connectionCopy"
                    class="workspace-status-pill"
                    :class="connectionCopy.tone"
                >
                    {{ connectionCopy.label }}
                </span>
            </div>

            <ol class="grid gap-3 lg:grid-cols-5">
                <li
                    v-for="(stage, index) in processingStages"
                    :key="stage.id"
                    class="relative rounded-xl border p-4"
                    :class="stageStateClass(stage.state)"
                >
                    <div
                        v-if="index < processingStages.length - 1"
                        class="pipeline-stage-connector hidden lg:block"
                    />

                    <div class="flex items-start gap-3">
                        <span
                            class="pipeline-stage-dot mt-1"
                            :class="stageDotClass(stage.state)"
                        />

                        <div>
                            <p
                                class="text-[0.68rem] font-semibold tracking-[0.14em] uppercase"
                            >
                                Stage {{ index + 1 }}
                            </p>
                            <p class="mt-1 font-semibold">
                                {{ stage.label }}
                            </p>
                            <p class="mt-2 text-xs/5 opacity-80">
                                {{ stage.description }}
                            </p>
                        </div>
                    </div>
                </li>
            </ol>
        </section>
    </div>
</template>
