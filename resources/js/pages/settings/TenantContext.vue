<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TenantContextController from '@/actions/App/Http/Controllers/Settings/TenantContextController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/tenant-context';
import { type BreadcrumbItem } from '@/types';

type TenantOption = {
    id: string;
    name: string;
    slug: string;
};

type Props = {
    tenants: TenantOption[];
    activeTenantId: string | null;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Tenant context',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Tenant context" />

        <h1 class="sr-only">Tenant Context Settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Tenant context"
                    description="Select the active tenant used for tenant-scoped pages"
                />

                <p
                    v-if="activeTenantId"
                    class="text-sm text-muted-foreground"
                >
                    Active tenant context is set. You can switch tenants at any
                    time.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    No tenant context selected. Tenant-scoped pages are blocked
                    until you choose one.
                </p>

                <Form
                    v-bind="TenantContextController.update.form()"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <div class="grid gap-2">
                        <Label for="tenant_id">Tenant</Label>
                        <select
                            id="tenant_id"
                            name="tenant_id"
                            required
                            :value="activeTenantId ?? ''"
                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="" disabled>Select a tenant</option>
                            <option
                                v-for="tenant in tenants"
                                :key="tenant.id"
                                :value="tenant.id"
                            >
                                {{ tenant.name }} ({{ tenant.slug }})
                            </option>
                        </select>
                        <InputError :message="errors.tenant_id" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="processing">Save context</Button>

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

                <div class="border-t pt-6">
                    <Form
                        v-bind="TenantContextController.destroy.form()"
                        v-slot="{ processing }"
                    >
                        <Button
                            variant="outline"
                            :disabled="processing || !activeTenantId"
                        >
                            Clear context
                        </Button>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
