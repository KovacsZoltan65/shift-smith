<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref } from "vue";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

import CreateModal from "@/Pages/Companies/CreateModal.vue";
import EditModal from "@/Pages/Companies/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";
const { has } = usePermissions();

const props = defineProps({
    title: { type: String, default: trans("companies.title") },
    filter: { type: Object, default: () => ({}) },
    endpointBase: { type: String, default: "/companies" },
    permissionPrefix: { type: String, default: "companies" },
    hqBadge: { type: String, default: "" },
    fetchRouteName: { type: String, default: "" },
    detailRouteName: { type: String, default: "" },
    forbiddenRedirectRouteName: { type: String, default: "" },
});

const title = computed(() => props.title || trans("companies.title"));

const canCreate = computed(() => has(`${props.permissionPrefix}.create`));
const canUpdate = computed(() => has(`${props.permissionPrefix}.update`));
const canDelete = computed(() => has(`${props.permissionPrefix}.delete`));
const canAnyRowAction = computed(() => canUpdate.value || canDelete.value);

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editCompany = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const forbiddenHandled = ref(false);
const dt = ref(null);

const rows = ref([]);

// Táblázat state
const selected = ref([]);

// Sor műveletek
const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        disabled: actionLoading.value || !canUpdate.value,
        command: () => openEditModal(row),
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: actionLoading.value || !canDelete.value,
        command: () => confirmDeleteOne(row),
    },
];

// Szűrő állapot
const globalFilterFields = ["name", "email", "phone", "active"];
const booleanOptions = [
    { label: trans("true"), value: true },
    { label: trans("false"), value: false },
];
const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    email: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    phone: {
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

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    editCompany.value = row;
    editOpen.value = true;
};

const onSaved = async (msg = trans("common.success")) => {
    selected.value = [];
    await fetchCompanies();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: msg,
        life: 2000,
    });
};

// A HQ lista és a tenant lista ugyanazt a komponenst használja, ezért az endpointok feloldása konfigurálható.
const fetchUrl = () => {
    const query = {
        page: 1,
        per_page: 100,
        field: "name",
        order: "asc",
    };

    if (props.fetchRouteName) {
        return route(props.fetchRouteName, query);
    }

    return `${props.endpointBase}/fetch?${new URLSearchParams(query).toString()}`;
};

const resolveDetailUrl = (id) => {
    if (props.detailRouteName) {
        return route(props.detailRouteName, id);
    }

    return `${props.endpointBase}/${id}`;
};

const handleForbidden = () => {
    if (forbiddenHandled.value) return;
    forbiddenHandled.value = true;

    toast.add({
        severity: "warn",
        summary: trans("companies.messages.permission_denied"),
        detail: trans("companies.messages.hq_permission_required"),
        life: 3500,
    });

    if (props.forbiddenRedirectRouteName) {
        setTimeout(() => {
            window.location.assign(route(props.forbiddenRedirectRouteName));
        }, 250);
    }
};

const fetchCompanies = async () => {
    if (forbiddenHandled.value) return;

    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(fetchUrl(), {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (res.status === 403) {
            handleForbidden();
            return;
        }

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();

        // A lista adaptere egyszerre kezeli a régi és az egységesített backend payload formátumot.
        const items = Array.isArray(json?.data)
            ? json.data
            : (json?.data?.data ?? []);

        rows.value = items;
    } catch (e) {
        error.value = e?.message || trans("common.unknown_error");
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: trans("companies.dialogs.delete_confirm", { name: row.name }),
        header: trans("common.confirmation"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => deleteOne(row.id),
    });
};

const deleteOne = async (id) => {
    actionLoading.value = true;

    try {
        const res = await csrfFetch(resolveDetailUrl(id), {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) {
            let msg = trans("companies.messages.delete_failed_http", {
                status: res.status,
            });
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("companies.messages.deleted_success"),
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchCompanies();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("common.unknown_error"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const confirmBulkDelete = () => {
    const ids = (selected.value ?? []).map((x) => x.id);
    if (!ids.length) return;

    confirm.require({
        message: trans("companies.dialogs.bulk_delete_confirm", {
            count: ids.length,
        }),
        header: trans("companies.actions.bulk_delete"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => bulkDelete(ids),
    });
};

const bulkDelete = async (ids) => {
    actionLoading.value = true;

    try {
        const res = await csrfFetch(`${props.endpointBase}/destroy_bulk`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ ids }),
        });

        if (!res.ok) {
            let msg = trans("companies.messages.bulk_delete_failed_http", {
                status: res.status,
            });
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("companies.messages.bulk_deleted_success", {
                count: ids.length,
            }),
            life: 2500,
        });

        selected.value = [];
        await fetchCompanies();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("common.unknown_error"),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

onMounted(() => {
    initFilters();
    fetchCompanies();
});
</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <CreateModal v-model="createOpen" @saved="onSaved" :canCreate="canCreate" />

    <EditModal
        v-model="editOpen"
        :company="editCompany"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <span
                        v-if="hqBadge"
                        class="inline-flex items-center rounded bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700"
                    >
                        {{ hqBadge }}
                    </span>

                    <Button
                        v-if="canCreate"
                        :label="$t('companies.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="companies-create"
                    />

                    <Button
                        :label="$t('companies.actions.refresh')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        size="small"
                        :disabled="loading || actionLoading"
                        :loading="loading"
                        @click="fetchCompanies"
                        data-testid="companies-refresh"
                    />

                    <Button
                        v-if="canDelete"
                        :label="$t('companies.actions.bulk_delete')"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="
                            !selected?.length || actionLoading || loading
                        "
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                        data-testid="companies-bulk-delete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        {{
                            $t("companies.selected_count", {
                                count: selected.length,
                            })
                        }}
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">{{ $t("common.error") }}</div>
                <div class="text-sm">{{ error }}</div>
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
                :loading="loading || actionLoading"
                sortMode="multiple"
                removableSort
                filterDisplay="menu"
                :globalFilterFields="globalFilterFields"
            >
                <template #header>
                    <div class="flex justify-between">
                        <Button
                            type="button"
                            icon="pi pi-filter-slash"
                            :label="$t('companies.filters.clear')"
                            variant="outlined"
                            @click="clearFilters()"
                        />
                        <IconField>
                            <InputIcon>
                                <i class="pi pi-search" />
                            </InputIcon>
                            <InputText
                                v-model="filters['global'].value"
                                :placeholder="
                                    $t('companies.filters.keyword_search')
                                "
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{ $t("companies.states.empty") }}</template>
                <template #loading>{{
                    $t("companies.states.loading")
                }}</template>

                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column
                    field="id"
                    :header="$t('columns.id')"
                    sortable
                    style="width: 90px"
                />
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
                            :placeholder="$t('companies.filters.name')"
                        />
                    </template>
                </Column>
                <Column
                    field="email"
                    filterField="email"
                    :header="$t('columns.email')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('companies.filters.email')"
                        />
                    </template>
                </Column>
                <Column
                    field="phone"
                    filterField="phone"
                    :header="$t('columns.phone')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="$t('companies.filters.phone')"
                        />
                    </template>
                </Column>
                <Column
                    field="active"
                    filterField="active"
                    :header="$t('columns.active')"
                    filter
                    sortable
                    style="width: 120px"
                    dataType="boolean"
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <span
                            class="inline-flex items-center rounded px-2 py-1 text-xs"
                            :class="
                                data.active
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-600'
                            "
                        >
                            {{ data.active ? $t("true") : $t("false") }}
                        </span>
                    </template>
                    <template #filter="{ filterModel }">
                        <Select
                            v-model="filterModel.value"
                            :options="booleanOptions"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full"
                            showClear
                            :placeholder="$t('companies.filters.status')"
                        />
                    </template>
                </Column>

                <Column
                    v-if="canAnyRowAction"
                    :header="$t('columns.actions')"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                :buttonTitle="
                                    $t('companies.actions.edit_title', {
                                        name: data.name,
                                    })
                                "
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
