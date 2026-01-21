<script setup>
import { onMounted, ref, computed, watch } from "vue";
import { router, Head, usePage } from "@inertiajs/vue3";

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

import CreateModal from "@/Pages/Users/CreateModal.vue";
import EditModal from "@/Pages/Users/EditModal.vue";
import PasswordResetModal from "@/Pages/Users/PasswordResetModal.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const page = usePage();

const props = defineProps({
    title: String,
    filter: Object,
});

const createOpen = ref(false);
const editOpen = ref(false);
const pwOpen = ref(false);
const editUser = ref(null);
const pwUser = ref(null);

const toast = useToast();
const confirm = useConfirm();

const loading = ref(false);
const actionLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);

// checkbox selection
const selected = ref([]);

const authUserId = computed(() => Number(page.props.auth?.user?.id ?? 0));

// ------------------------
const rowMenu = ref();
const rowMenuModel = ref([]);
const rowMenuRow = ref(null);

const openRowMenu = (event, row) => {
    rowMenuRow.value = row;

    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            command: () => openEditModal(row),
        },
        {
            label: "Törlés",
            icon: "pi pi-trash",
            disabled: isSelf(row) || actionLoading.value,
            command: () => confirmDeleteOne(row),
        },
        {
            label: "Jelszó módosítás",
            icon: "pi pi-key",
            command: () => openPasswordResetModal(row),
        },
    ];

    rowMenu.value.toggle(event);
};
// ------------------------

const isSelf = (row) => {
    const rowId = Number(row?.id ?? 0);
    const me = authUserId.value;

    return rowId === me;
};

// lazy state
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

const onSaved = async () => {
    selected.value = [];
    await fetchUsers();
    toast.add({ severity: "success", summary: "Siker", detail: "Mentve.", life: 2000 });
};

const openEditModal = (row) => {
    editUser.value = row;
    editOpen.value = true;
};

const openPasswordResetModal = (row) => {
    pwUser.value = row;
    pwOpen.value = true;
};

const onSearchInput = () => {
    if (t) clearTimeout(t);
    t = setTimeout(() => {
        lazy.value.first = 0;
        lazy.value.page = 0;
        fetchUsers();
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

const fetchUsers = async () => {
    loading.value = true;
    error.value = null;

    try {
        const res = await fetch(`/users/fetch?${buildQuery()}`, {
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

//const csrf = () =>
//    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const deleteOne = async (id) => {
    actionLoading.value = true;
    try {
        const res = await csrfFetch(`/users/${id}`, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });
        /*
        const res = await fetch(`/users/${id}`, {
            method: "DELETE",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
                Accept: "application/json",
            },
        });
        */

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
            detail: "Felhasználó törölve",
            life: 2500,
        });

        // ha a törölt benne volt a kijelölésben, vedd ki
        selected.value = selected.value.filter((x) => x.id !== id);

        await fetchUsers();
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

const onPasswordResetSent = () => {
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: "Email elküldve.",
        life: 2500,
    });
};

const confirmDeleteOne = (row) => {
    console.log("Delete One");
    confirm.require({
        message: `Biztos törlöd: ${row.name} (${row.email})?`,
        header: "Megerősítés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => deleteOne(row.id),
    });
};

const bulkDelete = async (ids) => {
    actionLoading.value = true;
    try {
        const res = await fetch(`/users/bulk`, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
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
        await fetchUsers();
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
        message: `Biztos törlöd a kijelölt ${ids.length} felhasználót?`,
        header: "Bulk törlés",
        icon: "pi pi-exclamation-triangle",
        acceptLabel: "Törlés",
        rejectLabel: "Mégse",
        acceptClass: "p-button-danger",
        accept: () => bulkDelete(ids),
    });
};

const goEdit = (row) => {
    // ha van named route:
    // router.visit(route("users.edit", row.id));
    router.visit(`/users/${row.id}/edit`);
};

onMounted(fetchUsers);
</script>

<template>
    <Head title="Felhasználók" />

    <AuthenticatedLayout>
        <Toast />
        <ConfirmDialog />

        <!-- CREATE MODAL -->
        <CreateModal v-model="createOpen" @saved="onSaved" />

        <!-- EDIT MODAL -->
        <EditModal v-model="editOpen" :user="editUser" @saved="onSaved" />

        <!-- PASSWORD RESET MODAL -->
        <PasswordResetModal v-model="pwOpen" :user="pwUser" @sent="onPasswordResetSent" />

        <div class="p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        label="Új felhasználó"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />

                    <Button
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
                :rowSelectable="(row) => !isSelf(row)"
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
                <Column
                    selectionMode="multiple"
                    headerStyle="width: 3rem"
                    :disabledSelection="(row) => isSelf(row)"
                />

                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column field="name" header="Név" sortable />
                <Column field="email" header="Email" sortable />
                <Column field="created_at" header="Létrehozva" sortable>
                    <template #body="{ data }">
                        {{ formatDate(data.created_at) }}
                    </template>
                </Column>

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
