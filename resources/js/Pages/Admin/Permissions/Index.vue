<script setup>
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";

import CreateModal from "@/Pages/Admin/Permissions/CreateModal.vue";
import EditModal from "@/Pages/Admin/Permissions/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";
const { has } = usePermissions();
const canCreate = has("permissions.create");
const canUpdate = has("permissions.update");
const canDelete = has("permissions.delete");

const props = defineProps({
    filter: { type: Object, default: () => ({}) },

    // index() inertia propsból jön:
    permissions: { type: Array, default: () => [] }, // [{id,name}]
    defaultGuard: { type: String, default: "web" },
});

const title = trans("permissions.title");

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editPermission = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);
const dt = ref(null);

const rows = ref([]);

// checkbox selection
const selected = ref([]);

// ------------------------
// Row actions menu
const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        disabled: actionLoading.value || !canUpdate,
        command: () => openEditModal(row),
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: actionLoading.value || !canDelete,
        command: () => confirmDeleteOne(row),
    },
];
// ------------------------

const globalFilterFields = ["name", "guard_name", "users_count"];
const createInitialFilters = () => ({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS },
    name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
    },
    guard_name: {
        operator: FilterOperator.AND,
        constraints: [{ value: null, matchMode: FilterMatchMode.CONTAINS }],
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

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    (async () => {
        actionLoading.value = true;
        try {
            const res = await fetch(`/admin/permissions/${row.id}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            editPermission.value = json?.data ?? json;
            editOpen.value = true;
        } catch (e) {
            toast.add({
                severity: "error",
                summary: trans("common.error"),
                detail: e?.message || trans("permissions.messages.show_failed"),
                life: 3500,
            });
        } finally {
            actionLoading.value = false;
        }
    })();
};

const onSaved = async (msg = trans("common.success")) => {
    selected.value = [];
    await fetchPermissions();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: msg,
        life: 2000,
    });
};

const fetchPermissions = async () => {
    loading.value = true;
    error.value = null;

    try {
        const query = new URLSearchParams({
            page: "1",
            per_page: "100",
            field: "name",
            order: "asc",
        }).toString();

        const res = await fetch(`/admin/permissions/fetch?${query}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();

        /**
         * NÁLAD így jön:
         * {
         *   data: { current_page, data: [ ... ] },
         *   meta: { total, ... },
         *   filter: { ... }
         * }
         *
         * Tehát a rekord tömb: json.data.data
         */
        const items = Array.isArray(json?.data)
            ? json.data
            : (json?.data?.data ?? []);

        rows.value = items;
    } catch (e) {
        error.value = e?.message || trans("common.unknown_error");
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: trans("permissions.dialogs.delete_confirm", {
            name: row.name,
        }),
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
        const res = await csrfFetch(`/admin/permissions/${id}`, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) {
            let msg = trans("permissions.messages.delete_failed_http", {
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
            detail: trans("permissions.messages.deleted_success"),
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchPermissions();
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
        message: trans("permissions.dialogs.bulk_delete_confirm", {
            count: ids.length,
        }),
        header: trans("permissions.actions.bulk_delete"),
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
        // Ha még nincs ilyen route, akkor vagy add hozzá, vagy vedd ki a bulkDelete gombot.
        const res = await csrfFetch(`/admin/permissions/destroy_bulk`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({ ids }),
        });

        if (!res.ok) {
            let msg = trans("permissions.messages.bulk_delete_failed_http", {
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
            detail: trans("permissions.messages.bulk_deleted_success", {
                count: ids.length,
            }),
            life: 2500,
        });

        selected.value = [];
        await fetchPermissions();
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
    fetchPermissions();
});
</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <!-- CREATE MODAL -->
    <CreateModal
        v-model="createOpen"
        :defaultGuard="defaultGuard"
        :canCreate="canCreate"
        @saved="onSaved"
    />

    <!-- EDIT MODAL -->
    <EditModal
        v-model="editOpen"
        :permission="editPermission"
        :defaultGuard="defaultGuard"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <!-- CREATE -->
                    <Button
                        v-if="canCreate"
                        :label="trans('permissions.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="permissions-create"
                    />

                    <!-- BULK DELETE -->
                    <Button
                        v-if="canDelete"
                        :label="trans('permissions.actions.bulk_delete')"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="
                            !selected?.length || actionLoading || loading
                        "
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        {{
                            trans("permissions.selected_count", {
                                count: selected.length,
                            })
                        }}
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">{{ trans("common.error") }}</div>
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
                            :label="trans('permissions.filters.clear')"
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
                                    trans('permissions.filters.keyword_search')
                                "
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{
                    trans("permissions.states.empty")
                }}</template>
                <template #loading>{{
                    trans("permissions.states.loading")
                }}</template>

                <!-- checkbox oszlop -->
                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column
                    field="id"
                    :header="trans('columns.id')"
                    sortable
                    style="width: 90px"
                />
                <Column
                    field="name"
                    filterField="name"
                    :header="trans('columns.name')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="trans('permissions.filters.name')"
                        />
                    </template>
                </Column>

                <Column
                    field="guard_name"
                    filterField="guard_name"
                    :header="trans('permissions.fields.guard_name')"
                    filter
                    sortable
                    style="width: 140px"
                    :showFilterMatchModes="false"
                >
                    <template #body="{ data }">
                        <Tag :value="data.guard_name" />
                    </template>
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="
                                trans('permissions.filters.guard_name')
                            "
                        />
                    </template>
                </Column>

                <Column
                    field="users_count"
                    :header="trans('permissions.fields.users')"
                    sortable
                    style="width: 120px"
                />

                <Column
                    field="created_at"
                    :header="trans('columns.created_at')"
                    sortable
                    style="width: 220px"
                />

                <!-- Actions -->
                <Column
                    :header="trans('columns.actions')"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                :buttonTitle="
                                    trans('permissions.actions.edit_title', {
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
