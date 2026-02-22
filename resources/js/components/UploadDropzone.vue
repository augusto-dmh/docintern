<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    documentSurfaceClass,
    documentTypographyClass,
} from '@/lib/document-experience';
import type { DocumentExperienceGuardrails } from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    disabled?: boolean;
    serverError?: string;
};

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    serverError: '',
});

const emit = defineEmits<{
    'file-selected': [file: File];
    'file-cleared': [];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const localError = ref('');
const isDragActive = ref(false);

const maxFileSizeBytes = 100 * 1024 * 1024;
const allowedExtensions = new Set([
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'jpg',
    'jpeg',
    'png',
]);

const selectedFileDetails = computed(() => {
    if (!selectedFile.value) {
        return '';
    }

    const size = selectedFile.value.size;
    const sizeInMb = size / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(size / 1024))} KB`;
});

const dropzoneClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'group relative cursor-pointer overflow-hidden p-6 transition',
    ),
);

const selectedFileClass = computed(() =>
    documentSurfaceClass(
        props.documentExperience,
        { reveal: false },
        'flex items-center justify-between gap-3 px-4 py-3',
    ),
);

const dropzoneTitleClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-lg font-semibold',
    ),
);

const dropzoneSubtleClass = computed(() =>
    documentTypographyClass(props.documentExperience, 'subtle'),
);

const selectedFileNameClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'title',
        'text-sm font-semibold',
    ),
);

const selectedFileSizeClass = computed(() =>
    documentTypographyClass(props.documentExperience, 'subtle', 'text-xs'),
);

const removeButtonClass = computed(() =>
    documentTypographyClass(
        props.documentExperience,
        'seal',
        'text-sm font-medium hover:underline',
    ),
);

function openFilePicker(): void {
    if (props.disabled) {
        return;
    }

    fileInput.value?.click();
}

function onFileInputChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    processSelectedFile(file);
}

function onDrop(event: DragEvent): void {
    event.preventDefault();
    isDragActive.value = false;

    if (props.disabled) {
        return;
    }

    const file = event.dataTransfer?.files?.[0] ?? null;

    processSelectedFile(file);
}

function onDragOver(event: DragEvent): void {
    event.preventDefault();

    if (!props.disabled) {
        isDragActive.value = true;
    }
}

function onDragLeave(): void {
    isDragActive.value = false;
}

function processSelectedFile(file: File | null): void {
    localError.value = '';

    if (!file) {
        selectedFile.value = null;
        emit('file-cleared');
        return;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!allowedExtensions.has(extension)) {
        selectedFile.value = null;
        localError.value =
            'Unsupported file type. Use pdf, doc, docx, xls, xlsx, jpg, jpeg, or png.';
        emit('file-cleared');

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return;
    }

    if (file.size > maxFileSizeBytes) {
        selectedFile.value = null;
        localError.value = 'File must be 100MB or smaller.';
        emit('file-cleared');

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        return;
    }

    selectedFile.value = file;
    emit('file-selected', file);
}

function clearSelection(): void {
    selectedFile.value = null;
    localError.value = '';

    if (fileInput.value) {
        fileInput.value.value = '';
    }

    emit('file-cleared');
}
</script>

<template>
    <div class="space-y-3">
        <input
            ref="fileInput"
            type="file"
            class="sr-only"
            :disabled="disabled"
            @change="onFileInputChange"
        />

        <div
            role="button"
            tabindex="0"
            :aria-disabled="disabled"
            :class="[
                dropzoneClass,
                {
                    'ring-2 ring-[var(--doc-seal)]/25': isDragActive,
                    'opacity-60': disabled,
                    'hover:-translate-y-0.5 hover:shadow-lg': !disabled,
                },
            ]"
            @click="openFilePicker"
            @keydown.enter.prevent="openFilePicker"
            @keydown.space.prevent="openFilePicker"
            @drop="onDrop"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
        >
            <div
                class="absolute inset-y-0 left-0 w-1 bg-[var(--doc-seal)]/70"
            />

            <p :class="dropzoneTitleClass">Drop file to archive</p>
            <p :class="[dropzoneSubtleClass, 'mt-2 text-sm']">
                PDF, Word, Excel, JPG, PNG up to 100MB.
            </p>
            <p
                :class="[
                    dropzoneSubtleClass,
                    'mt-1 text-xs tracking-[0.14em] uppercase',
                ]"
            >
                or click to choose a single file
            </p>
        </div>

        <div v-if="selectedFile" :class="selectedFileClass">
            <div>
                <p :class="selectedFileNameClass">
                    {{ selectedFile.name }}
                </p>
                <p :class="selectedFileSizeClass">{{ selectedFileDetails }}</p>
            </div>

            <button
                type="button"
                :class="removeButtonClass"
                @click="clearSelection"
            >
                Remove
            </button>
        </div>

        <p v-if="localError" class="text-sm text-destructive">
            {{ localError }}
        </p>
        <p v-else-if="serverError" class="text-sm text-destructive">
            {{ serverError }}
        </p>
    </div>
</template>
