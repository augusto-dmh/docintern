<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UploadDropzone from '@/components/UploadDropzone.vue';
import UploadProgressTracker from '@/components/UploadProgressTracker.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Matter } from '@/types';

const props = defineProps<{
    matter: Matter;
}>();

const permissions = usePage().props.auth.permissions;
const canCreateDocuments = permissions.includes('create documents');

const form = useForm<{
    title: string;
    file: File | null;
}>({
    title: '',
    file: null,
});

const selectedFile = ref<File | null>(null);

const uploadItems = computed(() => {
    if (!selectedFile.value) {
        return [];
    }

    const hasFileError = Boolean(form.errors.file);
    const progress = form.processing ? (form.progress?.percentage ?? 12) : 100;
    const status = form.processing
        ? 'uploading'
        : hasFileError
          ? 'failed'
          : 'completed';

    return [
        {
            name: selectedFile.value.name,
            size: selectedFile.value.size,
            progress,
            status,
        },
    ];
});

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
        href: MatterController.show.url(props.matter),
    },
    {
        title: 'Upload Document',
    },
];

function onFileSelected(file: File): void {
    selectedFile.value = file;
    form.file = file;
}

function onFileCleared(): void {
    selectedFile.value = null;
    form.file = null;
}

function submit(): void {
    if (!canCreateDocuments || !form.file) {
        return;
    }

    form.submit(DocumentController.store(props.matter), {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Upload Document" />

        <div class="documents-experience rounded-3xl p-6 sm:p-8">
            <section class="doc-hero doc-fade-up p-6 sm:p-8">
                <p
                    class="doc-seal text-xs font-semibold tracking-[0.18em] uppercase"
                >
                    Matter archive
                </p>
                <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                    Upload legal document
                </h1>
                <p class="doc-subtle mt-3 max-w-2xl text-sm sm:text-base">
                    Add supporting files to <strong>{{ matter.title }}</strong>
                    with private S3 storage and audit tracking.
                </p>
            </section>

            <section
                class="doc-surface doc-fade-up doc-delay-1 mt-6 p-6 sm:p-8"
            >
                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label
                            for="title"
                            class="doc-title text-sm font-semibold"
                        >
                            Document title
                        </Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            name="title"
                            required
                            autocomplete="off"
                            placeholder="e.g. Retainer agreement"
                            class="border-[var(--doc-border)] bg-[hsl(38_50%_98%)]"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2">
                        <Label class="doc-title text-sm font-semibold"
                            >File</Label
                        >
                        <UploadDropzone
                            :disabled="form.processing"
                            :server-error="form.errors.file"
                            @file-selected="onFileSelected"
                            @file-cleared="onFileCleared"
                        />
                        <UploadProgressTracker :items="uploadItems" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                !canCreateDocuments ||
                                !form.file
                            "
                            class="bg-[var(--doc-seal)] text-white hover:bg-[hsl(9_72%_30%)]"
                        >
                            {{
                                form.processing
                                    ? 'Uploading...'
                                    : 'Upload Document'
                            }}
                        </Button>

                        <Button as-child type="button" variant="outline">
                            <Link :href="DocumentController.index()"
                                >Back to documents</Link
                            >
                        </Button>
                    </div>
                </form>
            </section>
        </div>
    </AppLayout>
</template>
