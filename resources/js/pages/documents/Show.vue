<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Document } from '@/types';

const props = defineProps<{
    document: Document;
}>();

const permissions = usePage().props.auth.permissions;
const canEditDocuments = permissions.includes('edit documents');

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

function formatFileSize(bytes: number): string {
    const sizeInMb = bytes / (1024 * 1024);

    if (sizeInMb >= 1) {
        return `${sizeInMb.toFixed(2)} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="document.title" />

        <div class="documents-experience rounded-3xl p-6 sm:p-8">
            <section class="doc-hero doc-fade-up p-6 sm:p-8">
                <p
                    class="doc-seal text-xs font-semibold tracking-[0.18em] uppercase"
                >
                    Case document
                </p>
                <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                    {{ document.title }}
                </h1>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <Button
                        as-child
                        class="bg-[var(--doc-seal)] text-white hover:bg-[hsl(9_72%_30%)]"
                    >
                        <Link :href="DocumentController.download(document)"
                            >Download</Link
                        >
                    </Button>

                    <Button v-if="canEditDocuments" as-child variant="outline">
                        <Link :href="DocumentController.edit(document)"
                            >Edit metadata</Link
                        >
                    </Button>
                </div>
            </section>

            <section
                class="doc-surface doc-fade-up doc-delay-1 mt-6 p-6 sm:p-8"
            >
                <dl class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            File name
                        </dt>
                        <dd class="doc-title mt-1 text-base font-semibold">
                            {{ document.file_name }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            File size
                        </dt>
                        <dd class="mt-1 text-sm">
                            {{ formatFileSize(document.file_size) }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            MIME type
                        </dt>
                        <dd class="mt-1 text-sm">
                            {{ document.mime_type ?? 'Unknown' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            Status
                        </dt>
                        <dd class="mt-1 text-sm">
                            {{ document.status.replaceAll('_', ' ') }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            Matter
                        </dt>
                        <dd class="mt-1 text-sm">
                            <Link
                                v-if="document.matter"
                                :href="MatterController.show(document.matter)"
                                class="doc-seal hover:underline"
                            >
                                {{ document.matter.title }}
                            </Link>
                            <span v-else>—</span>
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            Uploaded by
                        </dt>
                        <dd class="mt-1 text-sm">
                            {{ document.uploader?.name ?? 'System' }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="doc-subtle text-xs font-semibold tracking-[0.12em] uppercase"
                        >
                            Recorded on
                        </dt>
                        <dd class="mt-1 text-sm">
                            {{ formatDate(document.created_at) }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </AppLayout>
</template>
