<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Matter, type PaginatedData } from '@/types';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

type Props = {
    matters: PaginatedData<Matter>;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Matters" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Matters</h1>
                <Button as-child>
                    <Link :href="MatterController.create()">New Matter</Link>
                </Button>
            </div>

            <div class="rounded-lg border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="px-4 py-3 text-left font-medium">Title</th>
                            <th class="px-4 py-3 text-left font-medium">Client</th>
                            <th class="px-4 py-3 text-left font-medium">Reference</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="matters.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                No matters found.
                            </td>
                        </tr>
                        <tr
                            v-for="matter in matters.data"
                            :key="matter.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="MatterController.show(matter)"
                                    class="font-medium text-foreground hover:underline"
                                >
                                    {{ matter.title }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ matter.client?.name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ matter.reference_number ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
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
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="MatterController.edit(matter)"
                                    class="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Edit
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="matters.last_page > 1" class="flex items-center justify-center gap-1">
                <template v-for="link in matters.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md px-3 py-1 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted'"
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="px-3 py-1 text-sm text-muted-foreground/50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>
