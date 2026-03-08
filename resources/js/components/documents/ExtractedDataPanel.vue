<script setup lang="ts">
import { computed } from 'vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import type {
    DocumentClassification,
    DocumentExperienceGuardrails,
    DocumentExtractedData,
} from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    classification: DocumentClassification | null;
    extractedData: DocumentExtractedData | null;
    delay?: 1 | 2 | null;
};

type FieldPair = {
    label: string;
    value: string;
};

const props = withDefaults(defineProps<Props>(), {
    delay: null,
});

const extractedLines = computed(() => {
    const payload = props.extractedData?.payload;
    const lines = Array.isArray(payload?.lines) ? payload.lines : [];

    if (lines.length > 0) {
        return lines
            .filter((line): line is string => typeof line === 'string')
            .map((line) => line.trim())
            .filter((line) => line !== '')
            .slice(0, 8);
    }

    return (props.extractedData?.extracted_text ?? '')
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '')
        .slice(0, 8);
});

const extractedFields = computed<FieldPair[]>(() => {
    const keyValues = Array.isArray(props.extractedData?.payload?.key_values)
        ? props.extractedData?.payload?.key_values
        : [];

    return keyValues
        .map((item) => {
            if (typeof item !== 'object' || item === null) {
                return null;
            }

            const label =
                typeof item.label === 'string' ? item.label.trim() : '';
            const value =
                typeof item.value === 'string' ? item.value.trim() : '';

            if (label === '' || value === '') {
                return null;
            }

            return { label, value };
        })
        .filter((item): item is FieldPair => item !== null)
        .slice(0, 8);
});

const metadataEntries = computed(() => {
    const metadata = props.extractedData?.metadata ?? {};

    return Object.entries(metadata)
        .filter(
            ([, value]) =>
                typeof value === 'string' || typeof value === 'number',
        )
        .slice(0, 4);
});

function formatConfidence(value: number | string | null | undefined): string {
    if (value === null || value === undefined) {
        return '—';
    }

    const numeric = typeof value === 'number' ? value : Number(value);

    if (Number.isNaN(numeric)) {
        return '—';
    }

    return `${(numeric * 100).toFixed(2)}%`;
}
</script>

<template>
    <DocumentExperienceSurface
        :document-experience="documentExperience"
        :delay="delay"
        class="p-6 sm:p-7"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p
                    class="doc-subtle text-xs font-semibold tracking-[0.14em] uppercase"
                >
                    Review evidence
                </p>
                <h2 class="doc-title mt-1 text-xl font-semibold">
                    Extracted data and classification
                </h2>
            </div>

            <span
                v-if="extractedData"
                class="rounded-full bg-[var(--doc-seal)]/10 px-3 py-1 text-xs font-semibold tracking-[0.12em] text-[var(--doc-seal)] uppercase"
            >
                {{ extractedData.provider }} extraction
            </span>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div
                class="rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(34_32%_97%/0.86)] p-4"
            >
                <p
                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                >
                    Classification
                </p>
                <p class="doc-title mt-2 text-base font-semibold">
                    {{ classification?.type ?? 'Pending classification' }}
                </p>
                <p class="doc-subtle mt-2 text-sm">
                    Confidence:
                    {{ formatConfidence(classification?.confidence) }}
                </p>
                <p class="doc-subtle mt-1 text-sm">
                    Provider:
                    {{ classification?.provider ?? 'Awaiting result' }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(34_32%_97%/0.86)] p-4"
            >
                <p
                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                >
                    Extracted lines
                </p>
                <p class="doc-title mt-2 text-base font-semibold">
                    {{ extractedLines.length }}
                </p>
                <p class="doc-subtle mt-2 text-sm">
                    Structured fields:
                    {{ extractedFields.length }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-[var(--doc-border)]/70 bg-[hsl(34_32%_97%/0.86)] p-4"
            >
                <p
                    class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                >
                    Metadata
                </p>
                <p class="doc-title mt-2 text-base font-semibold">
                    {{ metadataEntries.length > 0 ? 'Available' : 'Minimal' }}
                </p>
                <p class="doc-subtle mt-2 text-sm">
                    Source notes remain tenant-scoped and review-only.
                </p>
            </div>
        </div>

        <div class="mt-6 space-y-6">
            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3
                        class="doc-title text-sm font-semibold tracking-[0.14em] uppercase"
                    >
                        Extracted excerpt
                    </h3>
                </div>

                <div
                    v-if="extractedLines.length > 0"
                    class="space-y-2 rounded-[1.4rem] border border-[var(--doc-border)]/70 bg-[hsl(34_33%_98%/0.96)] p-4"
                >
                    <p
                        v-for="(line, index) in extractedLines"
                        :key="`${index}-${line}`"
                        class="text-sm leading-6"
                    >
                        {{ line }}
                    </p>
                </div>
                <p v-else class="doc-subtle text-sm">
                    Extracted text is not available yet. The review workspace
                    will refresh when processing completes.
                </p>
            </section>

            <section>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3
                        class="doc-title text-sm font-semibold tracking-[0.14em] uppercase"
                    >
                        Structured fields
                    </h3>
                </div>

                <dl
                    v-if="extractedFields.length > 0"
                    class="grid gap-3 rounded-[1.4rem] border border-[var(--doc-border)]/70 bg-[hsl(34_33%_98%/0.96)] p-4"
                >
                    <div
                        v-for="field in extractedFields"
                        :key="`${field.label}-${field.value}`"
                        class="grid gap-1 rounded-xl border border-[var(--doc-border)]/55 bg-white/85 p-3"
                    >
                        <dt
                            class="doc-subtle text-[11px] font-semibold tracking-[0.12em] uppercase"
                        >
                            {{ field.label }}
                        </dt>
                        <dd class="text-sm font-medium">
                            {{ field.value }}
                        </dd>
                    </div>
                </dl>
                <p v-else class="doc-subtle text-sm">
                    No key-value output is available for this file yet.
                </p>
            </section>

            <section v-if="metadataEntries.length > 0">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3
                        class="doc-title text-sm font-semibold tracking-[0.14em] uppercase"
                    >
                        Provider metadata
                    </h3>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="[label, value] in metadataEntries"
                        :key="label"
                        class="rounded-full border border-[var(--doc-border)]/70 bg-white/90 px-3 py-1.5 text-xs"
                    >
                        <span class="doc-subtle">{{ label }}:</span>
                        {{ value }}
                    </span>
                </div>
            </section>
        </div>
    </DocumentExperienceSurface>
</template>
