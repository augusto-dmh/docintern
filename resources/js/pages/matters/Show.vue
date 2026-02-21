<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Client, type Document, type Matter } from '@/types';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

type Props = {
    matter: Matter & { client: Client; documents: Document[] };
};

const props = defineProps<Props>();

const canEditMatters = usePage().props.auth.permissions.includes('edit matters');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="matter.title" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ matter.title }}</h1>
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300': matter.status === 'open',
                            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300': matter.status === 'closed',
                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300': matter.status === 'on_hold',
                        }"
                    >
                        {{ matter.status.replace('_', ' ') }}
                    </span>
                </div>
                <Button v-if="canEditMatters" as-child variant="outline">
                    <Link :href="MatterController.edit(matter)">Edit</Link>
                </Button>
            </div>

            <div class="rounded-lg border p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Client</dt>
                        <dd class="mt-1">
                            <Link
                                :href="ClientController.show(matter.client)"
                                class="text-foreground hover:underline"
                            >
                                {{ matter.client.name }}
                            </Link>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Reference Number</dt>
                        <dd class="mt-1">{{ matter.reference_number ?? '—' }}</dd>
                    </div>
                    <div v-if="matter.description" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">Description</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ matter.description }}</dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-4">
                <h2 class="text-lg font-semibold">Documents</h2>

                <div class="rounded-lg border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Title</th>
                                <th class="px-4 py-3 text-left font-medium">File</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="matter.documents.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                    No documents uploaded yet.
                                </td>
                            </tr>
                            <tr
                                v-for="document in matter.documents"
                                :key="document.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">{{ document.title }}</td>
                                <td class="px-4 py-3 text-muted-foreground">{{ document.file_name }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="{
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300': document.status === 'uploaded',
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300': document.status === 'ready_for_review',
                                            'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300': document.status === 'approved',
                                        }"
                                    >
                                        {{ document.status.replace(/_/g, ' ') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
