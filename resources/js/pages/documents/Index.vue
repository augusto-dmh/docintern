<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    type BreadcrumbItem,
    type Document,
    type PaginatedData,
} from '@/types';

defineProps<{
    documents: PaginatedData<Document>;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Documents" />

        <div class="space-y-4">
            <h1 class="text-2xl font-semibold">Documents</h1>
            <p class="text-sm text-muted-foreground">
                Document backend is active. Full document UI is implemented in
                the next PR.
            </p>
        </div>
    </AppLayout>
</template>
