<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import MatterController from '@/actions/App/Http/Controllers/MatterController';
import DocumentEmptyState from '@/components/documents/DocumentEmptyState.vue';
import DocumentExperienceFrame from '@/components/documents/DocumentExperienceFrame.vue';
import DocumentExperienceSurface from '@/components/documents/DocumentExperienceSurface.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { matterStatusToneClass } from '@/lib/document-experience';
import {
    type BreadcrumbItem,
    type Client,
    type DocumentExperienceGuardrails,
    type Matter,
} from '@/types';

type Props = {
    documentExperience: DocumentExperienceGuardrails;
    client: Client & { matters: Matter[] };
};

const props = defineProps<Props>();

const canEditClients =
    usePage().props.auth.permissions.includes('edit clients');

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Clients',
        href: ClientController.index.url(),
    },
    {
        title: props.client.name,
    },
];

function matterStatusClass(status: Matter['status']): string {
    return matterStatusToneClass(status);
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head :title="client.name" />

        <DocumentExperienceFrame
            :document-experience="documentExperience"
            eyebrow="Client profile"
            :title="client.name"
            description="Client records and matter routing in one place."
        >
            <template #actions>
                <Button v-if="canEditClients" as-child variant="outline">
                    <Link :href="ClientController.edit(client)">Edit</Link>
                </Button>
            </template>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="1"
                class="mt-6 p-6"
            >
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Email
                        </dt>
                        <dd class="mt-1">{{ client.email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Phone
                        </dt>
                        <dd class="mt-1">{{ client.phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Company
                        </dt>
                        <dd class="mt-1">{{ client.company ?? '—' }}</dd>
                    </div>
                    <div v-if="client.notes" class="sm:col-span-2">
                        <dt
                            class="doc-subtle text-sm font-medium tracking-[0.08em] uppercase"
                        >
                            Notes
                        </dt>
                        <dd class="doc-subtle mt-1 whitespace-pre-line">
                            {{ client.notes }}
                        </dd>
                    </div>
                </dl>
            </DocumentExperienceSurface>

            <DocumentExperienceSurface
                :document-experience="documentExperience"
                :delay="2"
                class="mt-6 p-4 sm:p-5"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="doc-title text-xl font-semibold">Matters</h2>
                    <span
                        class="doc-subtle text-xs tracking-[0.12em] uppercase"
                    >
                        {{ client.matters.length }} linked
                    </span>
                </div>

                <DocumentEmptyState
                    v-if="client.matters.length === 0"
                    :document-experience="documentExperience"
                    title="No matters for this client"
                    description="Create a matter to begin tracking related documents and activity."
                />

                <div v-else class="space-y-4">
                    <div class="grid gap-3 md:hidden">
                        <article
                            v-for="matter in client.matters"
                            :key="`client-mobile-${matter.id}`"
                            class="doc-grid-line rounded-xl border p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <Link
                                    :href="MatterController.show(matter)"
                                    class="doc-title text-base font-semibold hover:underline"
                                >
                                    {{ matter.title }}
                                </Link>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="matterStatusClass(matter.status)"
                                >
                                    {{ matter.status.replace('_', ' ') }}
                                </span>
                            </div>

                            <p class="doc-subtle mt-2 text-xs">
                                Ref {{ matter.reference_number ?? '—' }}
                            </p>
                            <p class="doc-subtle mt-1 text-xs">
                                {{ matter.documents_count ?? 0 }} documents
                            </p>
                        </article>
                    </div>

                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="doc-grid-line border-b bg-[hsl(37_38%_93%/0.75)]"
                                >
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Title
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Reference
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Documents
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold tracking-[0.12em] uppercase"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="matter in client.matters"
                                    :key="matter.id"
                                    class="doc-grid-line border-b last:border-0"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        <Link
                                            :href="
                                                MatterController.show(matter)
                                            "
                                            class="doc-title text-base font-semibold hover:underline"
                                        >
                                            {{ matter.title }}
                                        </Link>
                                    </td>
                                    <td class="doc-subtle px-4 py-3">
                                        {{ matter.reference_number ?? '—' }}
                                    </td>
                                    <td class="doc-subtle px-4 py-3">
                                        {{ matter.documents_count ?? 0 }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                matterStatusClass(matter.status)
                                            "
                                        >
                                            {{
                                                matter.status.replace('_', ' ')
                                            }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DocumentExperienceSurface>
        </DocumentExperienceFrame>
    </AppLayout>
</template>
