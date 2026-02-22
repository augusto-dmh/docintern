<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Document,
    type PaginatedData,
} from '@/types';

defineProps<{
    documents: PaginatedData<Document>;
}>();

const permissions = usePage().props.auth.permissions;
const canEditDocuments = permissions.includes('edit documents');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
];

function formatDate(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
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

function statusClass(status: Document['status']): string {
    if (status === 'approved') {
        return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300';
    }

    if (status === 'ready_for_review') {
        return 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300';
    }

    return 'bg-[var(--doc-seal)]/15 text-[var(--doc-seal)]';
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Documents" />

        <div class="documents-experience rounded-3xl p-6 sm:p-8">
            <section class="doc-hero doc-fade-up p-6 sm:p-8">
                <p
                    class="doc-seal text-xs font-semibold tracking-[0.18em] uppercase"
                >
                    Private repository
                </p>
                <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                    Document ledger
                </h1>
                <p class="doc-subtle mt-3 max-w-3xl text-sm sm:text-base">
                    Searchable matter documents with immutable storage and
                    traceable activity.
                </p>
            </section>

            <section
                class="doc-surface doc-fade-up doc-delay-1 mt-6 overflow-hidden"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="doc-grid-line border-b bg-[hsl(37_38%_93%/0.75)]"
                            >
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Title
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    File
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Matter
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Uploader
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Created
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-[0.12em] uppercase"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="documents.data.length === 0">
                                <td
                                    colspan="7"
                                    class="doc-subtle px-4 py-10 text-center"
                                >
                                    No documents found for this tenant.
                                </td>
                            </tr>

                            <tr
                                v-for="document in documents.data"
                                :key="document.id"
                                class="doc-grid-line border-b last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            DocumentController.show(document)
                                        "
                                        class="doc-title text-base font-semibold hover:underline"
                                    >
                                        {{ document.title }}
                                    </Link>
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    <p>{{ document.file_name }}</p>
                                    <p class="text-xs">
                                        {{ formatFileSize(document.file_size) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="document.matter"
                                        :href="
                                            MatterController.show(
                                                document.matter,
                                            )
                                        "
                                        class="doc-subtle hover:underline"
                                    >
                                        {{ document.matter.title }}
                                    </Link>
                                    <span v-else class="doc-subtle">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="statusClass(document.status)"
                                    >
                                        {{
                                            document.status.replaceAll('_', ' ')
                                        }}
                                    </span>
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ document.uploader?.name ?? 'System' }}
                                </td>
                                <td class="doc-subtle px-4 py-3">
                                    {{ formatDate(document.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-3">
                                        <Link
                                            :href="
                                                DocumentController.download(
                                                    document,
                                                )
                                            "
                                            class="doc-seal text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Download
                                        </Link>
                                        <Link
                                            v-if="canEditDocuments"
                                            :href="
                                                DocumentController.edit(
                                                    document,
                                                )
                                            "
                                            class="doc-subtle text-xs font-medium tracking-[0.12em] uppercase hover:underline"
                                        >
                                            Edit
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <nav
                v-if="documents.last_page > 1"
                class="doc-fade-up doc-delay-2 mt-6 flex flex-wrap items-center justify-center gap-2"
                aria-label="Documents pagination"
            >
                <template v-for="link in documents.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border border-[var(--doc-border)] px-3 py-1.5 text-sm transition"
                        :class="
                            link.active
                                ? 'border-[var(--doc-seal)] bg-[var(--doc-seal)] text-white'
                                : 'bg-[hsl(37_38%_96%)] hover:bg-[hsl(37_38%_93%)]'
                        "
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="rounded-md border border-[var(--doc-border)]/60 px-3 py-1.5 text-sm text-[var(--doc-muted)]/60"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>
