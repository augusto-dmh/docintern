<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem, type Document } from '@/types';

const props = defineProps<{
    document: Document;
}>();

const permissions = usePage().props.auth.permissions;
const canDeleteDocuments = permissions.includes('delete documents');

const form = useForm<{
    title: string;
}>({
    title: props.document.title,
});

const deleteForm = useForm({});
const deleteError = ref('');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Documents',
        href: DocumentController.index.url(),
    },
    {
        title: props.document.title,
        href: DocumentController.show.url(props.document),
    },
    {
        title: 'Edit',
    },
];

function updateDocument(): void {
    form.submit(DocumentController.update(props.document), {
        preserveScroll: true,
    });
}

function deleteDocument(): void {
    deleteError.value = '';

    if (!canDeleteDocuments) {
        deleteError.value = 'You do not have permission to delete documents.';
        return;
    }

    if (!window.confirm('Delete this document permanently?')) {
        return;
    }

    deleteForm.submit(DocumentController.destroy(props.document), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="`Edit ${document.title}`" />

        <div class="documents-experience rounded-3xl p-6 sm:p-8">
            <section class="doc-hero doc-fade-up p-6 sm:p-8">
                <p
                    class="doc-seal text-xs font-semibold tracking-[0.18em] uppercase"
                >
                    Metadata revision
                </p>
                <h1 class="doc-title mt-2 text-3xl font-semibold sm:text-4xl">
                    Edit document record
                </h1>
                <p class="doc-subtle mt-3 text-sm sm:text-base">
                    Update document title without changing the archived file.
                </p>
            </section>

            <section
                class="doc-surface doc-fade-up doc-delay-1 mt-6 p-6 sm:p-8"
            >
                <form class="space-y-6" @submit.prevent="updateDocument">
                    <div class="grid gap-2">
                        <Label
                            for="title"
                            class="doc-title text-sm font-semibold"
                        >
                            Document title
                        </Label>
                        <Input
                            id="title"
                            v-model="form.title"
                            required
                            placeholder="Document title"
                            class="border-[var(--doc-border)] bg-[hsl(38_50%_98%)]"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[var(--doc-seal)] text-white hover:bg-[hsl(9_72%_30%)]"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>

                        <Button as-child variant="outline" type="button">
                            <Link :href="DocumentController.show(document)"
                                >Cancel</Link
                            >
                        </Button>
                    </div>
                </form>
            </section>

            <section
                v-if="canDeleteDocuments"
                class="doc-surface doc-fade-up doc-delay-2 mt-6 border-destructive/30 p-6 sm:p-8"
            >
                <h2 class="doc-title text-xl font-semibold">
                    Destructive action
                </h2>
                <p class="doc-subtle mt-2 text-sm">
                    Deleting removes the file from storage and cannot be undone.
                </p>

                <Button
                    type="button"
                    variant="destructive"
                    class="mt-4"
                    :disabled="deleteForm.processing"
                    @click="deleteDocument"
                >
                    {{
                        deleteForm.processing
                            ? 'Deleting...'
                            : 'Delete Document'
                    }}
                </Button>

                <p v-if="deleteError" class="mt-2 text-sm text-destructive">
                    {{ deleteError }}
                </p>
            </section>
        </div>
    </AppLayout>
</template>
