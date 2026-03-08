<script setup lang="ts">
import { GlobalWorkerOptions, getDocument } from 'pdfjs-dist';
import pdfWorkerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import type { DocumentExperienceGuardrails } from '@/types';

GlobalWorkerOptions.workerSrc = pdfWorkerUrl;

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    src: string;
    title: string;
    delay?: 1 | 2 | null;
};

type PdfDocumentHandle = {
    numPages: number;
    getPage: (pageNumber: number) => Promise<{
        getViewport: (options: { scale: number }) => {
            width: number;
            height: number;
        };
        render: (options: {
            canvasContext: CanvasRenderingContext2D;
            transform: [number, number, number, number, number, number] | null;
            viewport: {
                width: number;
                height: number;
            };
        }) => { promise: Promise<void> };
    }>;
    destroy: () => Promise<void> | void;
};

type PdfLoadingTask = {
    promise: Promise<PdfDocumentHandle>;
    destroy?: () => void;
};

const props = withDefaults(defineProps<Props>(), {
    delay: null,
});

const loading = ref(true);
const errorMessage = ref<string | null>(null);
const zoom = ref(1.1);
const pageNumbers = ref<number[]>([]);

const canvasElements = new Map<number, HTMLCanvasElement>();
let documentHandle: PdfDocumentHandle | null = null;
let loadingTask: PdfLoadingTask | null = null;
let renderToken = 0;

const zoomLabel = computed(() => `${Math.round(zoom.value * 100)}%`);

function setCanvasRef(
    pageNumber: number,
    element: HTMLCanvasElement | null,
): void {
    if (element === null) {
        canvasElements.delete(pageNumber);

        return;
    }

    canvasElements.set(pageNumber, element);
}

function zoomOut(): void {
    zoom.value = Math.max(0.8, Number((zoom.value - 0.1).toFixed(1)));
}

function zoomIn(): void {
    zoom.value = Math.min(1.8, Number((zoom.value + 0.1).toFixed(1)));
}

async function destroyDocumentHandle(): Promise<void> {
    renderToken += 1;
    pageNumbers.value = [];
    canvasElements.clear();

    if (loadingTask?.destroy) {
        loadingTask.destroy();
    }

    loadingTask = null;

    if (documentHandle !== null) {
        await documentHandle.destroy();
        documentHandle = null;
    }
}

async function renderPages(): Promise<void> {
    if (documentHandle === null) {
        return;
    }

    const token = ++renderToken;

    await nextTick();

    for (const pageNumber of pageNumbers.value) {
        if (token !== renderToken || documentHandle === null) {
            return;
        }

        const canvas = canvasElements.get(pageNumber);

        if (canvas === undefined) {
            continue;
        }

        const page = await documentHandle.getPage(pageNumber);

        if (token !== renderToken) {
            return;
        }

        const viewport = page.getViewport({ scale: zoom.value });
        const context = canvas.getContext('2d');

        if (context === null) {
            continue;
        }

        const outputScale = window.devicePixelRatio || 1;

        canvas.width = Math.floor(viewport.width * outputScale);
        canvas.height = Math.floor(viewport.height * outputScale);
        canvas.style.width = `${viewport.width}px`;
        canvas.style.height = `${viewport.height}px`;

        await page.render({
            canvasContext: context,
            transform:
                outputScale === 1
                    ? null
                    : [outputScale, 0, 0, outputScale, 0, 0],
            viewport,
        }).promise;
    }
}

async function loadDocument(): Promise<void> {
    loading.value = true;
    errorMessage.value = null;

    await destroyDocumentHandle();

    try {
        loadingTask = getDocument(props.src) as PdfLoadingTask;
        documentHandle = await loadingTask.promise;
        pageNumbers.value = Array.from(
            { length: documentHandle.numPages },
            (_, index) => index + 1,
        );

        await renderPages();
    } catch (error) {
        errorMessage.value =
            error instanceof Error
                ? error.message
                : 'The PDF preview could not be loaded.';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    await loadDocument();
});

watch(
    () => props.src,
    async () => {
        await loadDocument();
    },
);

watch(zoom, async () => {
    if (documentHandle === null) {
        return;
    }

    await renderPages();
});

onBeforeUnmount(async () => {
    await destroyDocumentHandle();
});
</script>

<template>
    <DocumentExperienceSurface
        :document-experience="documentExperience"
        :delay="delay"
        class="overflow-hidden p-0"
    >
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--doc-border)]/70 px-5 py-4 sm:px-6"
        >
            <div>
                <p
                    class="doc-subtle text-xs font-semibold tracking-[0.14em] uppercase"
                >
                    Inline preview
                </p>
                <h2 class="doc-title mt-1 text-lg font-semibold">
                    {{ title }}
                </h2>
            </div>

            <div class="flex items-center gap-2">
                <span class="doc-subtle hidden text-xs sm:inline">
                    {{ pageNumbers.length }} pages
                </span>

                <button
                    type="button"
                    class="rounded-full border border-[var(--doc-border)]/80 px-3 py-1 text-sm transition hover:border-[var(--doc-seal)]/40 hover:text-[var(--doc-seal)]"
                    @click="zoomOut"
                >
                    -
                </button>
                <span class="min-w-14 text-center text-sm font-medium">
                    {{ zoomLabel }}
                </span>
                <button
                    type="button"
                    class="rounded-full border border-[var(--doc-border)]/80 px-3 py-1 text-sm transition hover:border-[var(--doc-seal)]/40 hover:text-[var(--doc-seal)]"
                    @click="zoomIn"
                >
                    +
                </button>
            </div>
        </div>

        <div class="bg-[hsl(32_37%_93%/0.85)] px-3 py-4 sm:px-5">
            <div
                v-if="loading"
                class="flex min-h-[28rem] items-center justify-center rounded-[1.6rem] border border-dashed border-[var(--doc-border)]/80 bg-white/70 px-6 text-center"
            >
                <div class="space-y-3">
                    <p class="doc-title text-base font-semibold">
                        Preparing the review copy
                    </p>
                    <p class="doc-subtle text-sm">
                        Loading the authenticated PDF preview for inline review.
                    </p>
                </div>
            </div>

            <div
                v-else-if="errorMessage"
                class="flex min-h-[28rem] items-center justify-center rounded-[1.6rem] border border-dashed border-red-200 bg-red-50/70 px-6 text-center"
            >
                <div class="space-y-3">
                    <p class="text-base font-semibold text-red-700">
                        Preview unavailable
                    </p>
                    <p class="text-sm text-red-600">
                        {{ errorMessage }}
                    </p>
                </div>
            </div>

            <div
                v-else
                class="max-h-[78vh] space-y-4 overflow-auto rounded-[1.6rem] border border-[var(--doc-border)]/70 bg-[hsl(34_33%_98%/0.96)] p-3 shadow-[inset_0_1px_0_hsl(0_0%_100%/0.75)]"
            >
                <article
                    v-for="pageNumber in pageNumbers"
                    :key="pageNumber"
                    class="rounded-[1.35rem] border border-[var(--doc-border)]/65 bg-white/95 p-3 shadow-[0_18px_40px_hsl(22_18%_45%/0.09)]"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <span
                            class="doc-subtle text-[11px] font-semibold tracking-[0.14em] uppercase"
                        >
                            Page {{ pageNumber }}
                        </span>
                    </div>

                    <div
                        class="overflow-auto rounded-[1rem] bg-[hsl(36_22%_92%/0.7)] p-2"
                    >
                        <canvas
                            :ref="
                                (element) =>
                                    setCanvasRef(
                                        pageNumber,
                                        element as HTMLCanvasElement | null,
                                    )
                            "
                            class="mx-auto block max-w-full rounded-[0.75rem] bg-white"
                        />
                    </div>
                </article>
            </div>
        </div>
    </DocumentExperienceSurface>
</template>
