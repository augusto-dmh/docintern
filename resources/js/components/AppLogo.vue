<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const tenant = usePage().props.tenant;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <img
            v-if="tenant?.logo_url"
            :src="tenant.logo_url"
            :alt="tenant.name"
            class="size-5 rounded-sm object-cover"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ tenant?.name ?? 'Laravel Starter Kit' }}
        </span>
    </div>
</template>
