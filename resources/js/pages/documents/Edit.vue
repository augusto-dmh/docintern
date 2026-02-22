<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Document } from '@/types';

const props = defineProps<{
    document: Document;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },
    {
        title: 'Edit',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${document.title}`" />

        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold">Edit Document</h1>

            <Form
                :action="`/documents/${props.document.id}`"
                method="put"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        :default-value="document.title"
                        required
                    />
                    <InputError :message="errors.title" />
                </div>

                <Button :disabled="processing">Save Changes</Button>
            </Form>
        </div>
    </AppLayout>
</template>
