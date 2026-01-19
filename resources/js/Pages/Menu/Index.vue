<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useAppMenu } from "@/composables/useAppMenu";

const { filteredMenu } = useAppMenu();
</script>

<template>
  <Head title="Menü" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">Menü</h2>
    </template>

    <div class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8">
      <div v-for="group in filteredMenu" :key="group.title" class="mb-8">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">
          {{ group.title }}
        </h3>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <template v-for="item in group.items" :key="item.route">
            <Link
              v-if="route().has(item.route)"
              :href="route(item.route)"
              class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-gray-300 hover:shadow"
            >
              <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
              <div class="mt-1 text-xs text-gray-500">{{ item.route }}</div>
            </Link>

            <div
              v-else
              class="rounded-lg border border-dashed border-gray-200 bg-white p-4 text-gray-400"
            >
              <div class="text-sm font-semibold">{{ item.title }}</div>
              <div class="mt-1 text-xs">(hiányzó route: {{ item.route }})</div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
