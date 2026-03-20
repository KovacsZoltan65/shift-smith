<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";
import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateModal from "@/Pages/Admin/LeaveCategories/Partials/CreateModal.vue";
import DeleteModal from "@/Pages/Admin/LeaveCategories/Partials/DeleteModal.vue";
import EditModal from "@/Pages/Admin/LeaveCategories/Partials/EditModal.vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";
import { usePermissions } from "@/composables/usePermissions";

import { useToast } from "primevue/usetoast";

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
});

const title = trans("leave_categories.title");

const { has } = usePermissions();
const toast = useToast();
const $t = trans;

const canCreate = computed(() => has("leave_categories.create"));
const canUpdate = computed(() => has("leave_categories.update"));
const canDelete = computed(() => has("leave_categories.delete"));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

const rows = ref([]);
const loading = ref(false);
const dt = ref(null);
const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editTarget = ref(null);
const deleteTarget = ref(null);
const globalFilterFields = ["code", "name", "description"];

const booleanOptions = [
    { label: trans("status.active"), value: true },
    { label: trans("status.inactive"), value: false },
];

const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    code: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    description: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    active: {
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
        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }

        return false;
    });
});

const fetchRows = async () => {
    loading.value = true;

    try {
        const { data } = await LeaveCategoryService.fetch({
            perPage: 100,
            sortBy: "order_index",
            sortDir: "asc",
        });

        rows.value = Array.isArray(data?.items) ? data.items : [];
    } catch (error) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail:
                error?.response?.data?.message ??
                error?.message ??
                trans("leave_categories.messages.fetch_failed"),
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const openEditModal = (row) => {
    editTarget.value = row;
    editOpen.value = true;
};

const openDeleteModal = (row) => {
    deleteTarget.value = row;
    deleteOpen.value = true;
};

const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        disabled: loading.value || !canUpdate.value,
        command: () => openEditModal(row),
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: loading.value || !canDelete.value,
        command: () => openDeleteModal(row),
    },
];

const onSaved = async (message) => {
    createOpen.value = false;
    editOpen.value = false;
    deleteOpen.value = false;
    editTarget.value = null;
    deleteTarget.value = null;
    await fetchRows();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: message,
        life: 2500,
    });
};

onMounted(() => {
    initFilters();
    fetchRows();
});
</script>

<template>
    <Head :title="title" />

    <Toast />

    <CreateModal v-model="createOpen" :canCreate="canCreate" @saved="onSaved" />
    <EditModal
        v-model="editOpen"
        :category="editTarget"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />
    <DeleteModal
        v-model="deleteOpen"
        :category="deleteTarget"
        :canDelete="canDelete"
        @deleted="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        v-if="canCreate"
                        :label="$t('leave_categories.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        data-testid="leave-categories-create"
                        @click="createOpen = true"
                    />

                    <Button
                        :label="$t('common.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :loading="loading"
                        data-testid="leave-categories-refresh"
                        @click="fetchRows"
                    />
                </div>
            </div>

            <DataTable
                ref="dt"
                :value="rows"
                dataKey="id"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :loading="loading"
                sortMode="multiple"
                removableSort
                v-model:filters="filters"
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
            >
                <template #header>
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="$t('leave_categories.filters.clear')"
                            severity="secondary"
                            size="small"
                            :disabled="!hasActiveFilters"
                            data-testid="leave-categories-clear-filters"
                            @click="clearFilters"
                        />
                        <span class="p-input-icon-left">
                            <i class="pi pi-search" />
                            <InputText
                                v-model="filters.global.value"
                                class="w-72"
                                :placeholder="$t('common.keyword_search')"
                                data-testid="leave-categories-search"
                            />
                        </span>
                    </div>
                </template>

                <template #empty>{{ $t("common.no_results") }}</template>
                <template #loading>{{ $t("common.loading") }}</template>

                <Column
                    field="code"
                    filterField="code"
                    :header="$t('columns.code')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('leave_categories.filters.code')"
                        />
                    </template>
                </Column>

                <Column
                    field="name"
                    filterField="name"
                    :header="$t('columns.name')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('leave_categories.filters.name')"
                        />
                    </template>
                </Column>

                <Column
                    field="description"
                    filterField="description"
                    :header="$t('columns.description')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <span class="text-sm text-slate-700">{{
                            data.description || "-"
                        }}</span>
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="
                                $t('leave_categories.filters.description')
                            "
                        />
                    </template>
                </Column>

                <Column
                    field="order_index"
                    :header="$t('columns.order_index')"
                    sortable
                />

                <Column
                    field="active"
                    filterField="active"
                    :header="$t('columns.active')"
                    filter
                    sortable
                    dataType="boolean"
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="
                                data.active
                                    ? $t('status.active')
                                    : $t('status.inactive')
                            "
                            :severity="data.active ? 'success' : 'danger'"
                        />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="booleanOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            :placeholder="$t('leave_categories.filters.status')"
                        />
                    </template>
                </Column>

                <Column
                    v-if="canAnyRowAction"
                    :header="$t('columns.actions')"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="loading"
                                :buttonTitle="$t('columns.actions')"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
