<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Matter } from '@/types';
import MatterController from '@/actions/App/Http/Controllers/MatterController';

type Props = {
    matter: Matter;
    clients: { id: number; name: string }[];
};

const props = defineProps<Props>();

const canDeleteMatters = usePage().props.auth.permissions.includes('delete matters');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Matters',
        href: MatterController.index.url(),
    },
    {
        title: props.matter.title,
        href: MatterController.show.url(props.matter),
    },
    {
        title: 'Edit',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${matter.title}`" />

        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold">Edit Matter</h1>

            <Form
                v-bind="MatterController.update.form(matter)"
                class="space-y-6"
                v-slot="{ errors, processing, recentlySuccessful }"
            >
                <div class="grid gap-2">
                    <Label for="client_id">Client</Label>
                    <select
                        id="client_id"
                        name="client_id"
                        required
                        :value="matter.client_id"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="">Select a client</option>
                        <option v-for="client in clients" :key="client.id" :value="client.id">
                            {{ client.name }}
                        </option>
                    </select>
                    <InputError :message="errors.client_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        :default-value="matter.title"
                        required
                        placeholder="Matter title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Matter description"
                        :value="matter.description ?? ''"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="reference_number">Reference Number</Label>
                    <Input
                        id="reference_number"
                        name="reference_number"
                        :default-value="matter.reference_number ?? ''"
                        placeholder="e.g. REF-001"
                    />
                    <InputError :message="errors.reference_number" />
                </div>

                <div class="grid gap-2">
                    <Label for="status">Status</Label>
                    <select
                        id="status"
                        name="status"
                        required
                        :value="matter.status"
                        class="border-input bg-background ring-offset-background focus-visible:ring-ring flex h-9 w-full rounded-md border px-3 py-1 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                        <option value="on_hold">On Hold</option>
                    </select>
                    <InputError :message="errors.status" />
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing">Save Changes</Button>

                    <Transition
                        enter-active-class="transition ease-in-out"
                        enter-from-class="opacity-0"
                        leave-active-class="transition ease-in-out"
                        leave-to-class="opacity-0"
                    >
                        <p v-show="recentlySuccessful" class="text-sm text-neutral-600">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </Form>

            <div v-if="canDeleteMatters" class="border-t pt-6">
                <Form
                    v-bind="MatterController.destroy.form(matter)"
                    v-slot="{ processing }"
                >
                    <Button variant="destructive" :disabled="processing">
                        Delete Matter
                    </Button>
                </Form>
            </div>
        </div>
    </AppLayout>
</template>
