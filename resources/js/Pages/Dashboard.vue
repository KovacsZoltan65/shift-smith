<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head } from "@inertiajs/vue3";

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentUsers: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="overflow-hidden bg-white shadow rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Users</div>
                        <div class="mt-2 text-3xl font-bold">
                            {{ props.stats.users ?? 0 }}
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Employees</div>
                        <div class="mt-2 text-3xl font-bold">
                            {{ props.stats.employees ?? 0 }}
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Companies</div>
                        <div class="mt-2 text-3xl font-bold">
                            {{ props.stats.companies ?? 0 }}
                        </div>
                    </div>

                    <div class="overflow-hidden bg-white shadow rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500">Work Shifts</div>
                        <div class="mt-2 text-3xl font-bold">
                            {{ props.stats.work_shifts ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="mt-8 bg-white shadow rounded-lg">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-lg font-medium text-gray-900">Recent Users</h3>
                    </div>
                    <div class="p-6">
                        <div class="flow-root">
                            <ul role="list" class="-my-5 divide-y divide-gray-200">
                                <li
                                    v-for="user in props.recentUsers"
                                    :key="user.id"
                                    class="py-4"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p
                                                class="text-sm font-medium text-gray-900 truncate"
                                            >
                                                {{ user.name }}
                                            </p>
                                            <p class="text-sm text-gray-500 truncate">
                                                {{ user.email }}
                                            </p>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{
                                                new Date(
                                                    user.created_at
                                                ).toLocaleDateString()
                                            }}
                                        </div>
                                    </div>
                                </li>
                                <li
                                    v-if="props.recentUsers.length === 0"
                                    class="py-4 text-sm text-gray-500"
                                >
                                    No recent users found.
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
