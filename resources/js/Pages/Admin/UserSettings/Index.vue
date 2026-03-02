<script setup>
import { Head, usePage } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UserService from "@/services/UserService.js";
import UserSettingsService from "@/services/UserSettingsService.js";
import { usePermissions } from "@/composables/usePermissions";

import Badge from "primevue/badge";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import Dropdown from "primevue/dropdown";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import Toolbar from "primevue/toolbar";
import { useToast } from "primevue/usetoast";

import BulkDeleteModal from "./Partials/BulkDeleteModal.vue";
import CreateModal from "./Partials/CreateModal.vue";
import DeleteModal from "./Partials/DeleteModal.vue";
import EditModal from "./Partials/EditModal.vue";

const props = defineProps({
    title: String,
    filter: Object,
    targetUserId: Number,
});

const page = usePage();
const { has } = usePermissions();
const toast = useToast();

const canCreate = computed(() => has("user_settings.create"));
const canUpdate = computed(() => has("user_settings.update"));
const canDelete = computed(() => has("user_settings.delete"));
const canDeleteAny = computed(() => has("user_settings.deleteAny"));
const canManageOthers = computed(() => has("user_settings.manageOthers"));
const companyName = computed(() => page.props.companyContext?.current_company?.name ?? "Company");

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const bulkDeleteOpen = ref(false);
const selected = ref([]);
const selectedItem = ref(null);
const loading = ref(false);
const rows = ref([]);
const totalRecords = ref(0);
const error = ref("");
const users = ref([]);
const groupOptions = ref([]);

const typeOptions = [
    { label: "int", value: "int" },
    { label: "bool", value: "bool" },
    { label: "string", value: "string" },
    { label: "json", value: "json" },
];

const filters = ref({
    q: props.filter?.q ?? "",
    group: props.filter?.group ?? null,
    type: props.filter?.type ?? null,
    user_id: props.filter?.user_id ?? props.targetUserId ?? page.props.auth?.user?.id ?? null,
});

const lazy = ref({
    first: 0,
    rows: Number(props.filter?.perPage ?? 10),
    page: Math.max(Number(props.filter?.page ?? 1) - 1, 0),
    sortField: props.filter?.sortBy ?? "key",
    sortOrder: props.filter?.sortDir === "desc" ? -1 : 1,
});

lazy.value.first = lazy.value.page * lazy.value.rows;

const fetchUsers = async () => {
    if (!canManageOthers.value) return;

    try {
        const { data } = await UserService.fetchUsersToSelect();
        users.value = (data ?? []).map((user) => ({
            label: `${user.name} (${user.email})`,
            value: user.id,
        }));
    } catch (_) {
        users.value = [];
    }
};

const fetchUserSettings = async () => {
    loading.value = true;
    error.value = "";

    try {
        const { data } = await UserSettingsService.fetch({
            q: filters.value.q?.trim() || undefined,
            group: filters.value.group || undefined,
            type: filters.value.type || undefined,
            user_id: filters.value.user_id || undefined,
            page: lazy.value.page + 1,
            perPage: lazy.value.rows,
            sortBy: lazy.value.sortField,
            sortDir: lazy.value.sortOrder === 1 ? "asc" : "desc",
        });

        rows.value = data?.items ?? [];
        totalRecords.value = Number(data?.meta?.total ?? 0);
        groupOptions.value = (data?.options?.groups ?? []).map((value) => ({ label: value, value }));
        lazy.value.page = Math.max(Number(data?.meta?.current_page ?? 1) - 1, 0);
        lazy.value.rows = Number(data?.meta?.per_page ?? lazy.value.rows);
        lazy.value.first = lazy.value.page * lazy.value.rows;
    } catch (err) {
        error.value = err?.response?.data?.message ?? err?.message ?? "Betöltési hiba";
    } finally {
        loading.value = false;
    }
};

const handleSaved = async (message) => {
    await fetchUserSettings();
    toast.add({ severity: "success", summary: "Siker", detail: message, life: 2500 });
};

const handleDeleted = async (message) => {
    selected.value = [];
    selectedItem.value = null;
    await fetchUserSettings();
    toast.add({ severity: "success", summary: "Siker", detail: message, life: 2500 });
};

const applyFilters = () => {
    lazy.value.page = 0;
    lazy.value.first = 0;
    fetchUserSettings();
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchUserSettings();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchUserSettings();
};

watch(() => filters.value.user_id, () => {
    if (canManageOthers.value) applyFilters();
});

onMounted(async () => {
    await fetchUsers();
    await fetchUserSettings();
});
</script>

<template>
    <Head :title="title" />
    <Toast />

    <CreateModal v-model="createOpen" :target-user-id="filters.user_id" @saved="handleSaved" />
    <EditModal v-model="editOpen" :user-setting-id="selectedItem?.id ?? null" :target-user-id="filters.user_id" @saved="handleSaved" />
    <DeleteModal v-model="deleteOpen" :item="selectedItem" :target-user-id="filters.user_id" @deleted="handleDeleted" />
    <BulkDeleteModal v-model="bulkDeleteOpen" :items="selected" :target-user-id="filters.user_id" @deleted="handleDeleted" />

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <Badge :value="companyName" severity="contrast" />
            </div>

            <Toolbar class="mb-4">
                <template #start>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button v-if="canCreate" label="Új" icon="pi pi-plus" @click="createOpen = true" />
                        <Button label="Frissítés" icon="pi pi-refresh" severity="secondary" :loading="loading" @click="fetchUserSettings" />
                        <Button v-if="canDeleteAny" label="Bulk delete" icon="pi pi-trash" severity="danger" :disabled="!selected.length" @click="bulkDeleteOpen = true" />
                    </div>
                </template>
                <template #end>
                    <div class="flex flex-wrap items-center gap-2">
                        <Dropdown
                            v-if="canManageOthers"
                            v-model="filters.user_id"
                            :options="users"
                            optionLabel="label"
                            optionValue="value"
                            placeholder="Felhasználó"
                            class="w-72"
                        />
                        <InputText v-model="filters.q" placeholder="Keresés kulcs / label / leírás" class="w-72" @keyup.enter="applyFilters" />
                        <Dropdown v-model="filters.group" :options="groupOptions" optionLabel="label" optionValue="value" placeholder="Csoport" showClear class="w-44" @change="applyFilters" />
                        <Dropdown v-model="filters.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Típus" showClear class="w-36" @change="applyFilters" />
                    </div>
                </template>
            </Toolbar>

            <div v-if="error" class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>

            <DataTable
                v-model:selection="selected"
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
                responsiveLayout="scroll"
                @page="onPage"
                @sort="onSort"
            >
                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="key" header="Kulcs" sortable />
                <Column field="group" header="Csoport" sortable />
                <Column field="type" header="Típus" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.type" severity="info" />
                    </template>
                </Column>
                <Column header="Érték">
                    <template #body="{ data }">
                        <span class="font-mono text-sm">{{ data.value_preview || "-" }}</span>
                    </template>
                </Column>
                <Column field="updated_at" header="Frissítve" sortable>
                    <template #body="{ data }">
                        {{ data.updated_at ? new Date(data.updated_at).toLocaleString() : "-" }}
                    </template>
                </Column>
                <Column header="" headerStyle="width: 12rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button v-if="canUpdate" size="small" severity="secondary" label="Szerkesztés" @click="selectedItem = data; editOpen = true" />
                            <Button v-if="canDelete" size="small" severity="danger" label="Törlés" @click="selectedItem = data; deleteOpen = true" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
