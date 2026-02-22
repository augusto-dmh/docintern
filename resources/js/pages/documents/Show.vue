<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Document } from '@/types';

defineProps<{
    document: Document;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },
    {
        title: 'Details',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="document.title" />

        <div class="space-y-4">
            <h1 class="text-2xl font-semibold">{{ document.title }}</h1>
            <p class="text-sm text-muted-foreground">
                File: {{ document.file_name }}
            </p>
            <p class="text-sm text-muted-foreground">
                Status: {{ document.status }}
            </p>
        </div>
    </AppLayout>
</template>
