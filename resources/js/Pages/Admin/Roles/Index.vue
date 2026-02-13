<script setup>
import { onMounted, ref } from "vue";
import { Head } from "@inertiajs/vue3";

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

import CreateModal from "@/Pages/Admin/Roles/CreateModal.vue";
import EditModal from "@/Pages/Admin/Roles/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";

import { usePermissions } from "@/composables/usePermissions";
const { has } = usePermissions();
const canCreate = has("roles.create");
const canUpdate = has("roles.update");
const canDelete = has("roles.delete");

const props = defineProps({
    title: { type: String, default: "Roles" },
    filter: { type: Object, default: () => ({}) },
});

const toast = useToast();
const confirm = useConfirm();

const createOpen = ref(false);
const editOpen = ref(false);
const editRole = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);

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

// lazy state (Companies minta)
const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "id",
    sortOrder: -1,
});

const search = ref(props.filter?.search ?? "");
let t = null;

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    // Edithez kell a permission_ids is -> kérjük le a részleteket
    (async () => {
        actionLoading.value = true;
        try {
            const res = await fetch(`/admin/roles/${row.id}`, {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    Accept: "application/json",
                },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            editRole.value = await res.json();
            editOpen.value = true;
        } catch (e) {
            toast.add({
                severity: "error",
                summary: "Hiba",
                detail: e?.message || "Nem sikerült a role betöltése.",
                life: 3500,
            });
        } finally {
            actionLoading.value = false;
        }
    })();
};

const onSaved = async (msg = "Mentve.") => {
    selected.value = [];
    await fetchRoles();
    toast.add({ severity: "success", summary: "Siker", detail: msg, life: 2000 });
};

const onSearchInput = () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchRoles();
    }, 300);
};

const buildQuery = () => {
    const order = lazy.value.sortOrder === 1 ? "asc" : "desc";

    const q = {
        ...(props.filter ?? {}),
        page: lazy.value.page + 1,
        per_page: lazy.value.rows,
        field: lazy.value.sortField,
        order,
        search: search.value?.trim() || "",
    };

    Object.keys(q).forEach((k) => {
        if (q[k] === null || q[k] === undefined || q[k] === "") delete q[k];
    });

    return new URLSearchParams(q).toString();
};

const fetchRoles = async () => {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/admin/roles/fetch?${buildQuery()}`, {
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
        rows.value = json?.data?.data ?? [];
        totalRecords.value = json?.meta?.total ?? 0;
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
        rows.value = [];
        totalRecords.value = 0;
    } finally {
        loading.value = false;
    }
};

const onPage = (event) => {
    lazy.value.first = event.first;
    lazy.value.rows = event.rows;
    lazy.value.page = event.page;
    fetchRoles();
};

const onSort = (event) => {
    lazy.value.sortField = event.sortField;
    lazy.value.sortOrder = event.sortOrder;
    lazy.value.first = 0;
    lazy.value.page = 0;
    fetchRoles();
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
        const res = await csrfFetch(`/admin/roles/${id}`, {
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
            detail: "Role törölve",
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchRoles();
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
        message: `Biztos törlöd a kijelölt ${ids.length} role-t?`,
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
        const res = await csrfFetch(`/admin/roles/destroy_bulk`, {
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
        await fetchRoles();
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

onMounted(fetchRoles);
</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <!-- CREATE MODAL -->
    <CreateModal v-model="createOpen" :canCreate="canCreate" @saved="onSaved" />

    <!-- EDIT MODAL -->
    <EditModal
        v-model="editOpen"
        :role="editRole"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AuthenticatedLayout>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <!-- CREATE -->
                    <Button
                        v-if="canCreate"
                        label="Új role"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="roles-create"
                    />

                    <!-- BULK DELETE -->
                    <Button
                        v-if="canDelete"
                        label="Kijelöltek törlése"
                        icon="pi pi-trash"
                        severity="danger"
                        size="small"
                        :disabled="!selected?.length || actionLoading || loading"
                        :loading="actionLoading"
                        @click="confirmBulkDelete"
                    />

                    <div v-if="selected?.length" class="text-sm text-gray-600">
                        Kijelölve: <b>{{ selected.length }}</b>
                    </div>
                </div>

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

            <Menu ref="rowMenu" :model="rowMenuModel" popup />

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
                :loading="loading || actionLoading"
                sortMode="single"
                :sortField="lazy.sortField"
                :sortOrder="lazy.sortOrder"
                @page="onPage"
                @sort="onSort"
                selectionMode="multiple"
            >
                <template #empty> Nincs találat. </template>

                <!-- checkbox oszlop -->
                <Column selectionMode="multiple" headerStyle="width: 3rem" />

                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column field="name" header="Név" sortable />

                <Column field="guard_name" header="Guard" sortable style="width: 140px">
                    <template #body="{ data }">
                        <Tag :value="data.guard_name" />
                    </template>
                </Column>

                <!-- ha a fetch már ad users_count-ot -->
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
