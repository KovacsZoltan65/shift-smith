<script setup>
import { Head } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import { computed, onMounted, reactive, ref } from "vue";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import TenantGroupFormFields from "@/Pages/TenantGroups/Partials/TenantGroupFormFields.vue";
import TenantGroupService from "@/services/TenantGroupService.js";
import ErrorService from "@/services/ErrorService.js";
import { usePermissions } from "@/composables/usePermissions";

import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import Column from "primevue/column";
import ConfirmDialog from "primevue/confirmdialog";
import DataTable from "primevue/datatable";
import Dialog from "primevue/dialog";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { IconField, InputIcon } from "primevue";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    title: { type: String, default: "Tenant Groups" },
    filter: { type: Object, default: () => ({}) },
});

const { has } = usePermissions();
const toast = useToast();
const confirm = useConfirm();

const canView = computed(() => has("tenant-groups.viewAny"));
const canCreate = computed(() => has("tenant-groups.create"));
const canUpdate = computed(() => has("tenant-groups.update"));
const canDelete = computed(() => has("tenant-groups.delete"));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

// Táblázat állapot
const rows = ref([]);
const loading = ref(false);
const tableError = ref(null);
const dt = ref(null);

// Dialog állapot
const dialogOpen = ref(false);
const dialogMode = ref("create");
const selectedRow = ref(null);
const formLoading = ref(false);
const formErrors = reactive({});

// Soronkénti műveleti menü állapot
const form = ref({
    name: "",
    code: "",
    status: null,
    active: true,
    notes: "",
});

// DataTable szűrőállapot
const localizedTitle = computed(() => trans("tenant_groups.title"));
const globalFilterFields = ["name", "code", "status", "active"];
const activeOptions = computed(() => [
    { label: trans("common.filters.all"), value: null },
    { label: trans("common.states.active"), value: true },
    { label: trans("common.states.inactive"), value: false },
]);
const statusOptions = computed(() => [
    { label: trans("common.filters.all"), value: null },
    { label: trans("tenant_groups.statuses.draft"), value: "draft" },
    { label: trans("tenant_groups.statuses.active"), value: "active" },
    { label: trans("tenant_groups.statuses.archived"), value: "archived" },
]);
const createTableFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    code: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    status: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
    active: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.EQUALS }],
    },
});
const filters = ref(createTableFilters());

const dialogTitle = computed(() =>
    dialogMode.value === "create"
        ? trans("tenant_groups.dialog.create_title")
        : trans("tenant_groups.dialog.edit_title"),
);

const resetForm = () => {
    form.value = {
        name: "",
        code: "",
        status: null,
        active: true,
        notes: "",
    };
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);
    selectedRow.value = null;
};

const openCreateDialog = () => {
    dialogMode.value = "create";
    resetForm();
    dialogOpen.value = true;
};

const buildRowMenuItems = (row) => [
        {
            label: trans("common.actions.edit"),
            icon: "pi pi-pencil",
            disabled: formLoading.value || !canUpdate.value,
            command: () => openEditDialog(row),
        },
        {
            label: trans("common.actions.delete"),
            icon: "pi pi-trash",
            disabled: formLoading.value || !canDelete.value,
            command: () => confirmDelete(row),
        },
    ];

// Szerkesztéskor friss backend olvasás történik, hogy a dialog a kanonikus rekordból töltsön,
// ne egy esetleg szűrt vagy elavult táblázati sorból.
const openEditDialog = async (row) => {
    dialogMode.value = "edit";
    resetForm();
    formLoading.value = true;
    dialogOpen.value = true;

    try {
        const response = await TenantGroupService.show(row.id);
        const data = response?.data?.data ?? response?.data;
        selectedRow.value = data;
        form.value = {
            name: data?.name ?? "",
            code: data?.code ?? "",
            status: data?.status ?? null,
            active: Boolean(data?.active ?? true),
            notes: data?.notes ?? "",
        };
    } catch (error) {
        dialogOpen.value = false;
        ErrorService.logClientError(error, { category: "tenant_group_show_failed" });
        toast.add({
            severity: "error",
            summary: trans("common.feedback.error"),
            detail: error?.response?.data?.message ?? trans("tenant_groups.feedback.load_failed"),
            life: 3500,
        });
    } finally {
        formLoading.value = false;
    }
};

const closeDialog = () => {
    dialogOpen.value = false;
    resetForm();
};

const clearFilters = () => {
    filters.value = createTableFilters();
    dt.value?.clearFilter?.();
};

// Csak arra szolgál, hogy a reset gomb inaktív legyen, ha a tábla már alapállapotban van.
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

// Betöltési folyamat
const loadRows = async () => {
    if (!canView.value) return;

    loading.value = true;
    tableError.value = null;

    try {
        const response = await TenantGroupService.fetch({
            sort_field: props.filter?.sort_field ?? "created_at",
            sort_direction: props.filter?.sort_direction ?? "desc",
            per_page: 100,
        });
        rows.value = response?.data?.data ?? [];
    } catch (error) {
        tableError.value = error?.response?.data?.message ?? trans("tenant_groups.feedback.fetch_failed");
        ErrorService.logClientError(error, { category: "tenant_group_fetch_failed" });
    } finally {
        loading.value = false;
    }
};

// A létrehozás és frissítés egy dialogot használ; a mód dönti el, melyik landlord végpont fut le.
const submit = async () => {
    formLoading.value = true;
    Object.keys(formErrors).forEach((key) => delete formErrors[key]);

    try {
        if (dialogMode.value === "create") {
            await TenantGroupService.store(form.value);
            toast.add({ severity: "success", summary: trans("common.feedback.saved"), detail: trans("tenant_groups.feedback.created"), life: 2500 });
        } else {
            await TenantGroupService.update(selectedRow.value.id, form.value);
            toast.add({ severity: "success", summary: trans("common.feedback.saved"), detail: trans("tenant_groups.feedback.updated"), life: 2500 });
        }

        closeDialog();
        await loadRows();
    } catch (error) {
        const normalizedErrors = error?.normalizedErrors || error?.response?.data?.errors || {};
        Object.keys(normalizedErrors).forEach((key) => {
            formErrors[key] = normalizedErrors[key]?.[0] ?? trans("validation.invalid_value");
        });

        if (Object.keys(normalizedErrors).length === 0) {
            formErrors._global = error?.response?.data?.message ?? trans("tenant_groups.feedback.save_failed");
        }

        ErrorService.logClientError(error, { category: "tenant_group_save_failed" });
    } finally {
        formLoading.value = false;
    }
};

// A destruktív műveletek megerősítést kapnak, mert az archiválást kapcsolódó cégek blokkolhatják,
// és ilyenkor a backend strukturált conflict választ ad vissza.
const confirmDelete = (row) => {
    confirm.require({
        header: trans("tenant_groups.actions.delete_confirm_title"),
        message: trans("tenant_groups.actions.delete_confirm_message", { name: row.name }),
        icon: "pi pi-exclamation-triangle",
        acceptClass: "p-button-danger",
        accept: async () => {
            try {
                await TenantGroupService.destroy(row.id);
                toast.add({ severity: "success", summary: trans("common.feedback.deleted"), detail: trans("tenant_groups.feedback.archived"), life: 2500 });
                await loadRows();
            } catch (error) {
                const detail = error?.response?.data?.message ?? trans("tenant_groups.feedback.delete_failed");
                toast.add({ severity: "error", summary: trans("common.feedback.blocked"), detail, life: 4000 });
                ErrorService.logClientError(error, { category: "tenant_group_delete_failed" });
            }
        },
    });
};

const activeSeverity = (active) => active ? "success" : "secondary";
const statusSeverity = (status) => {
    if (status === "active") return "success";
    if (status === "archived") return "secondary";
    return "contrast";
};

onMounted(loadRows);
</script>

<template>
    <Head :title="localizedTitle" />

    <Toast />
    <ConfirmDialog />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localizedTitle }}</h1>
                    <p class="text-sm text-slate-500">{{ $t("tenant_groups.description") }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        :label="$t('common.actions.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        :loading="loading"
                        @click="loadRows"
                    />
                    <Button
                        v-if="canCreate"
                        :label="$t('common.actions.create')"
                        icon="pi pi-plus"
                        @click="openCreateDialog"
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                <div v-if="tableError" class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 md:col-span-5">
                    {{ tableError }}
                </div>
            </div>

            <DataTable
                ref="dt"
                v-model:filters="filters"
                :value="rows"
                dataKey="id"
                :loading="loading"
                paginator
                :rows="10"
                :rowsPerPageOptions="[10, 25, 50, 100]"
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
                removableSort
            >
                <template #header>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="$t('common.actions.clear_filters')"
                            severity="secondary"
                            :disabled="!hasActiveFilters"
                            @click="clearFilters"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters.global.value"
                                class="w-full min-w-72"
                                :placeholder="$t('tenant_groups.search_placeholder')"
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>
                    <div class="py-8 text-center text-slate-500">{{ $t("tenant_groups.empty") }}</div>
                </template>
                <template #loading>
                    <div class="py-8 text-center text-slate-500">{{ $t("tenant_groups.loading") }}</div>
                </template>

                <Column field="id" :header="$t('common.fields.id')" sortable />
                <Column field="name" :header="$t('tenant_groups.fields.name')" sortable filter filterField="name" :showFilterMatchModes="false">
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('tenant_groups.filters.name_placeholder')"
                        />
                    </template>
                </Column>
                <Column field="code" :header="$t('tenant_groups.fields.code')" sortable filter filterField="code" :showFilterMatchModes="false">
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('tenant_groups.filters.code_placeholder')"
                        />
                    </template>
                </Column>
                <Column field="status" :header="$t('tenant_groups.fields.status')" sortable filter filterField="status" :showFilterMatchModes="false">
                    <template #body="{ data }">
                        <Tag :value="$t(`tenant_groups.statuses.${data.status || 'draft'}`)" :severity="statusSeverity(data.status)" />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            class="w-full"
                            :options="statusOptions"
                            optionLabel="label"
                            optionValue="value"
                            showClear
                            :placeholder="$t('tenant_groups.filters.status_placeholder')"
                        />
                    </template>
                </Column>
                <Column field="active" :header="$t('tenant_groups.fields.active')" sortable filter filterField="active" dataType="boolean" :showFilterMatchModes="false">
                    <template #body="{ data }">
                        <Tag :value="data.active ? $t('common.states.yes') : $t('common.states.no')" :severity="activeSeverity(data.active)" />
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            class="w-full"
                            :options="activeOptions"
                            optionLabel="label"
                            optionValue="value"
                            showClear
                            :placeholder="$t('tenant_groups.filters.active_placeholder')"
                        />
                    </template>
                </Column>
                <Column field="createdAt" :header="$t('common.fields.created_at')" sortable />
                <Column
                    v-if="canAnyRowAction"
                    :header="$t('common.fields.actions')"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="formLoading"
                                :buttonTitle="trans('tenant_groups.action_menu_title', { name: data.name })"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog :visible="dialogOpen" modal :header="dialogTitle" :style="{ width: '36rem' }" @update:visible="closeDialog">
            <div class="space-y-4">
                <div v-if="formErrors._global" class="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ formErrors._global }}
                </div>

                <TenantGroupFormFields
                    v-model="form"
                    :errors="formErrors"
                    :disabled="formLoading"
                />
            </div>

            <template #footer>
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-slate-500">
                        <Checkbox :binary="true" :modelValue="form.active" disabled />
                        <span>{{ $t("tenant_groups.active_flag_help") }}</span>
                    </div>
                    <div class="flex gap-2">
                        <Button :label="$t('common.actions.cancel')" severity="secondary" :disabled="formLoading" @click="closeDialog" />
                        <Button :label="$t('common.actions.save')" :loading="formLoading" @click="submit" />
                    </div>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
