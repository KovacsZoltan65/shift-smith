<script setup>
import { onMounted, ref } from "vue";
import { router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
  title: String,
  filter: Object,
});

const isLoading = ref(false);
const rows = ref([]);
const meta = ref({ current_page: 1, per_page: 10, total: 0, last_page: 1 });

const fetchUsers = async (params = {}) => {
  isLoading.value = true;

  const query = new URLSearchParams({
    ...props.filter,
    ...params,
  }).toString();

  const res = await fetch(`/users/fetch?${query}`, {
    headers: { "X-Requested-With": "XMLHttpRequest" },
  });

  const json = await res.json();

  rows.value = json.data;
  meta.value = json.meta;

  isLoading.value = false;
};

onMounted(() => fetchUsers());
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-6">
      <h1 class="text-2xl font-semibold mb-4">{{ title }}</h1>

      <div v-if="isLoading">Töltés...</div>

      <table v-else class="min-w-full border">
        <thead>
          <tr>
            <th class="border p-2 text-left">ID</th>
            <th class="border p-2 text-left">Név</th>
            <th class="border p-2 text-left">Email</th>
            <th class="border p-2 text-left">Létrehozva</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in rows" :key="u.id">
            <td class="border p-2">{{ u.id }}</td>
            <td class="border p-2">{{ u.name }}</td>
            <td class="border p-2">{{ u.email }}</td>
            <td class="border p-2">{{ u.created_at }}</td>
          </tr>
        </tbody>
      </table>

      <div class="mt-4 flex gap-2 items-center">
        <button
          class="border px-3 py-1"
          :disabled="meta.current_page <= 1 || isLoading"
          @click="fetchUsers({ page: meta.current_page - 1 })"
        >
          Prev
        </button>

        <span>
          Oldal: {{ meta.current_page }} / {{ meta.last_page }} (össz: {{ meta.total }})
        </span>

        <button
          class="border px-3 py-1"
          :disabled="meta.current_page >= meta.last_page || isLoading"
          @click="fetchUsers({ page: meta.current_page + 1 })"
        >
          Next
        </button>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
