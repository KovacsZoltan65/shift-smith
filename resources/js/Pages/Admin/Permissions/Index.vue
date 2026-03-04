<script setup>
import { computed, onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import Menu from "primevue/menu";
import Tag from "primevue/tag";

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
    title: { type: String, default: "Permissions" },
    filter: { type: Object, default: () => ({}) },

    // index() inertia propsból jön:
    permissions: { type: Array, default: () => [] }, // [{id,name}]
    defaultGuard: { type: String, default: "web" },
});

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
const rowMenu = ref();
const rowMenuModel = ref([]);
const rowMenuRow = ref(null);

const openRowMenu = (event, row) => {
    rowMenuRow.value = row;

    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => openEditModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: actionLoading.value || !canDelete,
            command: () => confirmDeleteOne(row),
        },
    ];

    rowMenu.value.toggle(event);
};
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
                summary: "Hiba",
                detail: e?.message || "Nem sikerült a permission betöltése.",
                life: 3500,
            });
        } finally {
            actionLoading.value = false;
        }
    })();
};

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchPermissions();
    toast.add({
        severity: "success",
        summary: "Siker",
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
        error.value = e?.message || "Ismeretlen hiba";
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: `Biztos törlöd: ${row.name}?`,
        header: "Megerősítés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
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
            let msg = `Törlés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Permission törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchPermissions();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Ismeretlen hiba",
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
        message: `Biztos törlöd a kijelölt ${ids.length} permission-t?`,
        header: "Bulk törlés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
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
            let msg = `Bulk törlés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        toast.add({
            severity: "success",
            summary: "Siker",
            detail: `Törölve: ${ids.length} db`,
            life: 2500,
        });

        selected.value = [];
        await fetchPermissions();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Ismeretlen hiba",
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
                        label="Új permission"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="permissions-create"
                    />

                    <!-- BULK DELETE -->
                    <Button
                        v-if="canDelete"
                        label="Kijelöltek törlése"
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
                        Kijelölve: <b>{{ selected.length }}</b>
                    </div>
                </div>
            </div>

            <div v-if="error" class="mb-3 border p-3">
                <div class="font-semibold">Hiba</div>
                <div class="text-sm">{{ error }}</div>
            </div>

            <Menu ref="rowMenu" :model="rowMenuModel" popup />

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
                selectionMode="multiple"
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

                <!-- checkbox oszlop -->
                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column
                    field="name"
                    filterField="name"
                    header="Név"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            placeholder="Nev keresese"
                        />
                    </template>
                </Column>

                <Column
                    field="guard_name"
                    filterField="guard_name"
                    header="Guard"
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
                            placeholder="Guard keresese"
                        />
                    </template>
                </Column>

                <Column
                    field="users_count"
                    header="Users"
                    sortable
                    style="width: 120px"
                />

                <Column
                    field="created_at"
                    header="Létrehozva"
                    sortable
                    style="width: 220px"
                />

                <!-- Actions -->
                <Column
                    header="Műveletek"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex gap-2 justify-end">
                            <Button
                                icon="pi pi-ellipsis-v"
                                severity="secondary"
                                size="small"
                                text
                                rounded
                                :disabled="actionLoading"
                                @click="openRowMenu($event, data)"
                                :title="`Műveletek: ${data.name}`"
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
