<script setup>
import { onMounted, ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import InputText from "primevue/inputtext";

const props = defineProps({
  title: String,
  filter: Object, // pl. { search, field, order, page, per_page } ha küldesz ilyet
});

const loading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);

// PrimeVue DataTable lazy state
const lazy = ref({
  first: 0, // offset
  rows: 10, // per page
  page: 0, // 0-index
  sortField: "id",
  sortOrder: -1, // 1=asc, -1=desc
});

const search = ref(props.filter?.search ?? "");

// debounce
let t = null;
const onSearchInput = () => {
  if (t) clearTimeout(t);
  t = setTimeout(() => {
    // új keresésnél vissza első oldalra
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchUsers();
  }, 300);
};

const buildQuery = () => {
  const order = lazy.value.sortOrder === 1 ? "asc" : "desc";

  const q = {
    ...(props.filter ?? {}),
    page: lazy.value.page + 1, // backend 1-index
    per_page: lazy.value.rows,
    field: lazy.value.sortField,
    order,
    search: search.value?.trim() || "",
  };

  // null/üres értékek kidobása
  Object.keys(q).forEach((k) => {
    if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
  });

  return new URLSearchParams(q).toString();
};

const fetchUsers = async () => {
  loading.value = true;
  error.value = null;

  try {
    const query = buildQuery();
    const res = await fetch(`/users/fetch?${query}`, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const json = await res.json();

    rows.value = json.data ?? [];
    totalRecords.value = json.meta?.total ?? 0;
  } catch (e) {
    error.value = e?.message || "Ismeretlen hiba";
  } finally {
    loading.value = false;
  }
};

const onPage = (event) => {
  lazy.value.first = event.first;
  lazy.value.rows = event.rows;
  lazy.value.page = event.page;
  fetchUsers();
};

const onSort = (event) => {
  lazy.value.sortField = event.sortField;
  lazy.value.sortOrder = event.sortOrder;
  lazy.value.first = 0;
  lazy.value.page = 0;
  fetchUsers();
};

const formatDate = (value) => {
  if (!value) return "";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return new Intl.DateTimeFormat("hu-HU", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
  }).format(d);
};

onMounted(fetchUsers);
</script>

<template>
  <AuthenticatedLayout>
    <div class="p-6">
      <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold">{{ title }}</h1>

        <span class="p-input-icon-left">
          <i class="pi pi-search" />
          <InputText
            v-model="search"
            placeholder="Keresés..."
            class="w-64"
            @input="onSearchInput"
          />
        </span>
      </div>

      <div v-if="error" class="mb-3 border p-3">
        <div class="font-semibold">Hiba</div>
        <div class="text-sm">{{ error }}</div>
      </div>

      <DataTable
        :value="rows"
        dataKey="id"
        lazy
        paginator
        :rows="lazy.rows"
        :first="lazy.first"
        :totalRecords="totalRecords"
        :rowsPerPageOptions="[10, 25, 50, 100]"
        :loading="loading"
        sortMode="single"
        :sortField="lazy.sortField"
        :sortOrder="lazy.sortOrder"
        @page="onPage"
        @sort="onSort"
        tableStyle="min-width: 50rem"
      >
        <template #empty> Nincs találat. </template>

        <Column field="id" header="ID" sortable style="width: 90px" />
        <Column field="name" header="Név" sortable />
        <Column field="email" header="Email" sortable />
        <Column field="created_at" header="Létrehozva" sortable>
          <template #body="{ data }">
            {{ formatDate(data.created_at) }}
          </template>
        </Column>
      </DataTable>
    </div>
  </AuthenticatedLayout>
</template>
