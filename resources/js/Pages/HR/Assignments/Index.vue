<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Cégek" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Tervezés</h2>
        </template>

        <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">
                Placeholder oldal – HR/Assignments/Index
            </div>
        </div>
    </AuthenticatedLayout>
</template>