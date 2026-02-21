<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Client, type PaginatedData } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

type Props = {
    clients: PaginatedData<Client>;
};

defineProps<Props>();

const permissions = usePage().props.auth.permissions;
const canCreateClients = permissions.includes('create clients');
const canEditClients = permissions.includes('edit clients');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Clients" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">Clients</h1>
                <Button v-if="canCreateClients" as-child>
                    <Link :href="ClientController.create()">New Client</Link>
                </Button>
            </div>

            <div class="rounded-lg border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Email</th>
                            <th class="px-4 py-3 text-left font-medium">Company</th>
                            <th class="px-4 py-3 text-left font-medium">Phone</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="clients.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                No clients found.
                            </td>
                        </tr>
                        <tr
                            v-for="client in clients.data"
                            :key="client.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="ClientController.show(client)"
                                    class="font-medium text-foreground hover:underline"
                                >
                                    {{ client.name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ client.email ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ client.company ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ client.phone ?? '—' }}
                            </td>
                            <td v-if="canEditClients" class="px-4 py-3 text-right">
                                <Link
                                    :href="ClientController.edit(client)"
                                    class="text-sm text-muted-foreground hover:text-foreground"
                                >
                                    Edit
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="clients.last_page > 1" class="flex items-center justify-center gap-1">
                <template v-for="link in clients.links" :key="link.label">
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
