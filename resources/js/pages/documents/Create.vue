<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Matter } from '@/types';

const props = defineProps<{
    matter: Matter;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: '/documents',
    },
    {
        title: 'Upload',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Upload Document" />

        <div class="mx-auto max-w-2xl space-y-6">
            <h1 class="text-2xl font-semibold">Upload Document</h1>

            <Form
                :action="`/matters/${props.matter.id}/documents`"
                method="post"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input
                        id="title"
                        name="title"
                        required
                        placeholder="Document title"
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="file">File</Label>
                    <Input id="file" type="file" name="file" required />
                    <InputError :message="errors.file" />
                </div>

                <Button :disabled="processing">Upload</Button>
            </Form>
        </div>
    </AppLayout>
</template>
