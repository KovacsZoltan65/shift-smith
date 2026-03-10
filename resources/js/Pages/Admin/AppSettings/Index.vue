<script setup>
import { Head, router } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { loadLanguageAsync } from "laravel-vue-i18n";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AppSettingsService from "@/services/AppSettingsService.js";
import { usePermissions } from "@/composables/usePermissions";

import Badge from "primevue/badge";
import Button from "primevue/button";
import Column from "primevue/column";
import DataTable from "primevue/datatable";
import InputText from "primevue/inputtext";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

import BulkDeleteModal from "./Partials/BulkDeleteModal.vue";
import CreateModal from "./Partials/CreateModal.vue";
import DeleteModal from "./Partials/DeleteModal.vue";
import EditModal from "./Partials/EditModal.vue";
import Select from "primevue/select";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    title: String,
    filter: Object,
});

const { has } = usePermissions();
const toast = useToast();

const canCreate = computed(() => has("app_settings.create"));
const canUpdate = computed(() => has("app_settings.update"));
const canDelete = computed(() => has("app_settings.delete"));
const canDeleteAny = computed(() => has("app_settings.deleteAny"));

const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const bulkDeleteOpen = ref(false);

const selected = ref([]);
const selectedItem = ref(null);
const loading = ref(false);
const rows = ref([]);
const error = ref("");
const dt = ref(null);

const groupOptions = ref([]);
const typeOptions = ref([
    { label: "int", value: "int" },
    { label: "bool", value: "bool" },
    { label: "string", value: "string" },
    { label: "select", value: "select" },
    { label: "json", value: "json" },
]);

const globalFilterFields = ["label", "key", "group", "type", "value_preview"];
const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    label: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    group: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    type: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
});
const filters = ref(createInitialFilters());

const initFilters = () => {
    filters.value = createInitialFilters();
};

const clearFilters = () => {
    initFilters();
    dt.value?.clearFilter?.();
};

const hasActiveFilters = computed(() => {
    const currentFilters = filters.value ?? {};
    return Object.values(currentFilters).some((entry) => {
        if (!entry || typeof entry !== "object") return false;
        if ("value" in entry) return entry.value !== null && entry.value !== "";
        if (Array.isArray(entry.constraints))
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        return false;
    });
});

const fetchAppSettings = async () => {
    loading.value = true;
    error.value = "";

    try {
        const { data } = await AppSettingsService.fetch({
            page: 1,
            perPage: 100,
            sortBy: "key",
            sortDir: "asc",
        });

        rows.value = data?.items ?? [];
        groupOptions.value = (data?.options?.groups ?? []).map((value) => ({
            label: value,
            value,
        }));
    } catch (err) {
        error.value =
            err?.response?.data?.message ?? err?.message ?? "Betöltési hiba";
    } finally {
        loading.value = false;
    }
};

const reloadEffectiveLocale = async (locale) => {
    if (locale) {
        await loadLanguageAsync(locale);
        document.documentElement.setAttribute("lang", locale);
    }

    await router.reload({ preserveState: true, preserveScroll: true });
};

const handleSaved = async (payload) => {
    const message = typeof payload === "string" ? payload : payload?.message;
    const item = typeof payload === "string" ? null : payload?.item;

    await fetchAppSettings();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: message,
        life: 2500,
    });

    if (item?.key === "app.locale") {
        await reloadEffectiveLocale(item?.value ?? null);
    }
};

const handleDeleted = async (message) => {
    selected.value = [];
    selectedItem.value = null;
    await fetchAppSettings();
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: message,
        life: 2500,
    });
};

const openEdit = (row) => {
    selectedItem.value = row;
    editOpen.value = true;
};

const openDelete = (row) => {
    selectedItem.value = row;
    deleteOpen.value = true;
};

const shortValue = (row) => row?.value_preview ?? "";
const updatedAt = (row) =>
    row?.updated_at ? new Date(row.updated_at).toLocaleString() : "-";
const typeSeverity = (type) => {
    if (type === "bool") return "success";
    if (type === "int") return "info";
    if (type === "select") return "contrast";
    if (type === "json") return "warning";
    return "secondary";
};

onMounted(() => {
    initFilters();
    fetchAppSettings();
});
</script>

<template>
    <Head :title="title" />

    <Toast />

    <CreateModal v-model="createOpen" @saved="handleSaved" />
    <EditModal
        v-model="editOpen"
        :app-setting-id="selectedItem?.id ?? null"
        @saved="handleSaved"
    />
    <DeleteModal
        v-model="deleteOpen"
        :item="selectedItem"
        @deleted="handleDeleted"
    />
    <BulkDeleteModal
        v-model="bulkDeleteOpen"
        :items="selected"
        @deleted="handleDeleted"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <Badge value="landlord" severity="contrast" />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="canCreate"
                    label="Új"
                    icon="pi pi-plus"
                    @click="createOpen = true"
                />
                <Button
                    label="Frissítés"
                    icon="pi pi-refresh"
                    severity="secondary"
                    :loading="loading"
                    @click="fetchAppSettings"
                />
                <Button
                    v-if="canDeleteAny"
                    label="Bulk delete"
                    icon="pi pi-trash"
                    severity="danger"
                    :disabled="!selected.length"
                    @click="bulkDeleteOpen = true"
                />
            </div>

            <div
                v-if="error"
                class="mb-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700"
            >
                {{ error }}
            </div>

            <DataTable
                ref="dt"
                v-model:selection="selected"
                v-model:filters="filters"
                :value="rows"
                dataKey="id"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading"
                sortMode="multiple"
                removableSort
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
                responsiveLayout="scroll"
            >
                <template #header>
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            label="Clear"
                            variant="outlined"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                placeholder="Keyword Search"
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>Nincs talalat.</template>
                <template #loading>Betoltes...</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column
                    field="label"
                    filterField="label"
                    header="Megnevezés"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        {{ data.label || data.key }}
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Megnevezes keresese"
                        />
                    </template>
                </Column>
                <Column
                    field="group"
                    filterField="group"
                    header="Csoport"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="groupOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            placeholder="Csoport"
                        />
                    </template>
                </Column>
                <Column
                    field="type"
                    filterField="type"
                    header="Típus"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="data.type"
                            :severity="typeSeverity(data.type)"
                        />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="typeOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            placeholder="Tipus"
                        />
                    </template>
                </Column>
                <Column header="Érték">
                    <template #body="{ data }">
                        <span class="font-mono text-sm">{{
                            shortValue(data) || "-"
                        }}</span>
                    </template>
                </Column>
                <Column field="updated_at" header="Frissítve" sortable>
                    <template #body="{ data }">
                        {{ updatedAt(data) }}
                    </template>
                </Column>
                <Column header="" headerStyle="width: 12rem">
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <Button
                                v-if="canUpdate"
                                size="small"
                                severity="secondary"
                                label="Szerkesztés"
                                @click="openEdit(data)"
                            />
                            <Button
                                v-if="canDelete"
                                size="small"
                                severity="danger"
                                label="Törlés"
                                @click="openDelete(data)"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
