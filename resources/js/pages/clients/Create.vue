<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import ClientController from '@/actions/App/Http/Controllers/ClientController';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: 'New Client',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="New Client" />

        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold">New Client</h1>

            <Form
                v-bind="ClientController.store.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" name="name" required placeholder="Client name" />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" type="email" name="email" placeholder="Email address" />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" name="phone" placeholder="Phone number" />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="company">Company</Label>
                    <Input id="company" name="company" placeholder="Company name" />
                    <InputError :message="errors.company" />
                </div>

                <div class="grid gap-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Additional notes"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing">Create Client</Button>
                </div>
            </Form>
        </div>
    </AppLayout>
</template>
