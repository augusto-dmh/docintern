<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Client, type Matter } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

type Props = {
    client: Client & { matters: Matter[] };
};

const props = defineProps<Props>();

const canEditClients = usePage().props.auth.permissions.includes('edit clients');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: props.client.name,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="client.name" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ client.name }}</h1>
                <Button v-if="canEditClients" as-child variant="outline">
                    <Link :href="ClientController.edit(client)">Edit</Link>
                </Button>
            </div>

            <div class="rounded-lg border p-6">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Email</dt>
                        <dd class="mt-1">{{ client.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Phone</dt>
                        <dd class="mt-1">{{ client.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-muted-foreground">Company</dt>
                        <dd class="mt-1">{{ client.company ?? '—' }}</dd>
                    </div>
                    <div v-if="client.notes" class="sm:col-span-2">
                        <dt class="text-sm font-medium text-muted-foreground">Notes</dt>
                        <dd class="mt-1 whitespace-pre-line">{{ client.notes }}</dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-4">
                <h2 class="text-lg font-semibold">Matters</h2>

                <div class="rounded-lg border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="px-4 py-3 text-left font-medium">Title</th>
                                <th class="px-4 py-3 text-left font-medium">Reference</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="client.matters.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-muted-foreground">
                                    No matters found for this client.
                                </td>
                            </tr>
                            <tr
                                v-for="matter in client.matters"
                                :key="matter.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-3 font-medium">{{ matter.title }}</td>
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
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
