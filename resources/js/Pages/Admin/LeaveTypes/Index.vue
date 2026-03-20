<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";
import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateModal from "@/Pages/Admin/LeaveTypes/Partials/CreateModal.vue";
import DeleteModal from "@/Pages/Admin/LeaveTypes/Partials/DeleteModal.vue";
import EditModal from "@/Pages/Admin/LeaveTypes/Partials/EditModal.vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";
import LeaveTypeService from "@/services/LeaveTypeService.js";
import { usePermissions } from "@/composables/usePermissions";

import { useToast } from "primevue/usetoast";
import { IconField, InputIcon } from "primevue";

const props = defineProps({
    filter: { type: Object, default: () => ({}) },
});

const pageTitle = trans("leave_types.title");

const { has } = usePermissions();
const toast = useToast();
const $t = trans;

const canCreate = computed(() => has("leave_types.create"));
const canUpdate = computed(() => has("leave_types.update"));
const canDelete = computed(() => has("leave_types.delete"));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

const rows = ref([]);
const loading = ref(false);
const dt = ref(null);
const createOpen = ref(false);
const editOpen = ref(false);
const deleteOpen = ref(false);
const editTarget = ref(null);
const deleteTarget = ref(null);
const categoryOptions = ref([]);
const globalFilterFields = ["code", "name", "category"];

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
    category: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
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
        if (!entry || typeof entry !== "object") {
            return false;
        }

        if ("value" in entry) {
            return entry.value !== null && entry.value !== "";
        }

        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }

        return false;
    });
});

const categoryLabel = (value) =>
    categoryOptions.value.find((option) => option.code === value)?.name ??
    value;

const loadCategoryOptions = async () => {
    try {
        const { data } = await LeaveCategoryService.selector({
            only_active: 0,
        });
        categoryOptions.value = Array.isArray(data?.data) ? data.data : [];
    } catch (_) {
        categoryOptions.value = [];
    }
};

const fetchRows = async () => {
    loading.value = true;

    try {
        const { data } = await LeaveTypeService.fetch({
            perPage: 100,
            sortBy: "name",
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
                trans("leave_types.messages.fetch_failed"),
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

onMounted(async () => {
    initFilters();
    await loadCategoryOptions();
    await fetchRows();
});
</script>

<template>
    <Head :title="pageTitle" />

    <Toast />

    <CreateModal
        v-model="createOpen"
        :canCreate="canCreate"
        :categoryOptions="categoryOptions"
        @saved="onSaved"
    />
    <EditModal
        v-model="editOpen"
        :leaveType="editTarget"
        :canUpdate="canUpdate"
        :categoryOptions="categoryOptions"
        @saved="onSaved"
    />
    <DeleteModal
        v-model="deleteOpen"
        :leaveType="deleteTarget"
        :canDelete="canDelete"
        @deleted="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ pageTitle }}</h1>

                    <Button
                        v-if="canCreate"
                        :label="$t('leave_types.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        data-testid="leave-types-create"
                        @click="createOpen = true"
                    />

                    <Button
                        :label="$t('common.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :loading="loading"
                        data-testid="leave-types-refresh"
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
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="$t('leave_types.filters.clear')"
                            variant="outlined"
                            data-testid="leave-types-clear-filters"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                :placeholder="$t('common.keyword_search')"
                                data-testid="leave-types-search"
                            />
                        </IconField>
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
                            :placeholder="$t('leave_types.filters.code')"
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
                            :placeholder="$t('leave_types.filters.name')"
                        />
                    </template>
                </Column>

                <Column
                    field="category"
                    filterField="category"
                    :header="$t('leave_types.fields.category')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Tag
                            :value="categoryLabel(data.category)"
                            severity="info"
                        />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="categoryOptions"
                            optionLabel="name"
                            optionValue="code"
                            class="w-full"
                            showClear
                            :placeholder="$t('leave_types.filters.category')"
                        />
                    </template>
                </Column>

                <Column
                    field="affects_leave_balance"
                    :header="$t('leave_types.fields.affects_leave_balance')"
                    sortable
                >
                    <template #body="{ data }">
                        <Tag
                            :value="
                                data.affects_leave_balance
                                    ? $t('leave_types.values.yes')
                                    : $t('leave_types.values.no')
                            "
                            :severity="
                                data.affects_leave_balance
                                    ? 'success'
                                    : 'secondary'
                            "
                        />
                    </template>
                </Column>

                <Column
                    field="requires_approval"
                    :header="$t('leave_types.fields.requires_approval')"
                    sortable
                >
                    <template #body="{ data }">
                        <Tag
                            :value="
                                data.requires_approval
                                    ? $t('leave_types.values.required')
                                    : $t('leave_types.values.no')
                            "
                            :severity="
                                data.requires_approval ? 'warning' : 'secondary'
                            "
                        />
                    </template>
                </Column>

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
                            :placeholder="$t('leave_types.filters.status')"
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
