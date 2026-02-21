<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Client } from '@/types';

type Props = {
    client: Client;
};

const props = defineProps<Props>();

const canDeleteClients =
    usePage().props.auth.permissions.includes('delete clients');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: props.client.name,
        href: ClientController.show.url(props.client),
    },
    {
        title: 'Edit',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${client.name}`" />

        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold">Edit Client</h1>

            <Form
                v-bind="ClientController.update.form(client)"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        :default-value="client.name"
                        required
                        placeholder="Client name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        :default-value="client.email ?? ''"
                        placeholder="Email address"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input
                        id="phone"
                        name="phone"
                        :default-value="client.phone ?? ''"
                        placeholder="Phone number"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="company">Company</Label>
                    <Input
                        id="company"
                        name="company"
                        :default-value="client.company ?? ''"
                        placeholder="Company name"
                    />
                    <InputError :message="errors.company" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Additional notes"
                        :value="client.notes ?? ''"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing">Save Changes</Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p
                            v-show="recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>

            <div v-if="canDeleteClients" class="border-t pt-6">
                <Form
                    v-bind="ClientController.destroy.form(client)"
                    v-slot="{ processing }"
                >
                    <Button variant="destructive" :disabled="processing">
                        Delete Client
                    </Button>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
