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
import Dialog from "primevue/dialog";
import MultiSelect from "primevue/multiselect";

import CreateModal from "@/Pages/Admin/Roles/CreateModal.vue";
import EditModal from "@/Pages/Admin/Roles/EditModal.vue";

import { csrfFetch } from "@/lib/csrfFetch";
import RoleService from "@/services/Auth/RoleService.js";
import UserService from "@/services/UserService.js";

import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";
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
const usersModalOpen = ref(false);
const editRole = ref(null);
const usersModalRole = ref(null);
const usersModalSelectedIds = ref([]);
const userOptions = ref([]);

const loading = ref(false);
const actionLoading = ref(false);
const usersModalLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const selected = ref([]);
const dt = ref(null);

const rowMenu = ref();
const rowMenuModel = ref([]);

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

const usersModalSummary = computed(() => {
    if (!usersModalRole.value) return "";

    const count = Array.isArray(usersModalSelectedIds.value)
        ? usersModalSelectedIds.value.length
        : 0;
    return `${count} felhasználó kijelölve`;
});

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            disabled: actionLoading.value || !canUpdate,
            command: () => openEditModal(row),
        },
        {
            label: "Felhasználók",
            icon: "pi pi-users",
            disabled: actionLoading.value || !canUpdate,
            command: () => openUsersModal(row),
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

const openCreate = () => {
    createOpen.value = true;
};

const openEditModal = (row) => {
    (async () => {
        actionLoading.value = true;
        try {
            const response = await RoleService.getRole(row.id);
            const json = response?.data ?? {};
            editRole.value = json?.data ?? json;
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
    toast.add({
        severity: "success",
        summary: "Siker",
        detail: msg,
        life: 2000,
    });
};

const fetchRoles = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await RoleService.getRoles({
            page: 1,
            per_page: 100,
            field: "name",
            order: "asc",
        });
        const json = response?.data ?? {};

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

const userOptionLabel = (user) => {
    if (!user) return "";
    return user.email ? `${user.name} (${user.email})` : user.name;
};

const ensureUserOptions = async () => {
    if (userOptions.value.length) return;

    const response = await UserService.fetchUsersToSelect();
    const items = Array.isArray(response?.data)
        ? response.data
        : (response?.data?.data ?? []);

    userOptions.value = items.map((user) => ({
        label: userOptionLabel(user),
        value: Number(user.id),
    }));
};

const openUsersModal = async (row) => {
    if (!canUpdate) return;

    usersModalLoading.value = true;

    try {
        await ensureUserOptions();
        const response = await RoleService.getRole(row.id);
        const payload = response?.data?.data ?? response?.data ?? {};

        usersModalRole.value = {
            id: Number(payload?.id ?? row.id),
            name: payload?.name ?? row.name,
            user_ids: Array.isArray(payload?.user_ids)
                ? payload.user_ids.map((id) => Number(id))
                : Array.isArray(row?.user_ids)
                  ? row.user_ids.map((id) => Number(id))
                  : [],
        };

        usersModalSelectedIds.value = [...usersModalRole.value.user_ids];
        usersModalOpen.value = true;
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail:
                e?.message || "Nem sikerült a role felhasználóit betölteni.",
            life: 3500,
        });
    } finally {
        usersModalLoading.value = false;
    }
};

const closeUsersModal = () => {
    usersModalOpen.value = false;
    usersModalRole.value = null;
    usersModalSelectedIds.value = [];
};

const saveUsersModal = async () => {
    if (!usersModalRole.value?.id) return;

    usersModalLoading.value = true;

    try {
        await RoleService.syncRoleUsers(
            Number(usersModalRole.value.id),
            usersModalSelectedIds.value.map((id) => Number(id)),
        );

        closeUsersModal();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "A role felhasználói frissítve.",
            life: 2500,
        });

        await fetchRoles();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail:
                e?.response?.data?.message ||
                e?.message ||
                "A mentés sikertelen.",
            life: 3500,
        });
    } finally {
        usersModalLoading.value = false;
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

onMounted(() => {
    initFilters();
    fetchRoles();
});
</script>

<template>
    <Head :title="title" />

    <Toast />
    <ConfirmDialog />

    <CreateModal v-model="createOpen" :canCreate="canCreate" @saved="onSaved" />

    <EditModal
        v-model="editOpen"
        :role="editRole"
        :canUpdate="canUpdate"
        @saved="onSaved"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        v-if="canCreate"
                        label="Új role"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                        data-testid="roles-create"
                    />

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
                :loading="loading || actionLoading || usersModalLoading"
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
                    style="width: 140px"
                >
                    <template #body="{ data }">
                        <Button
                            class="p-0"
                            text
                            :disabled="!canUpdate"
                            @click="openUsersModal(data)"
                        >
                            {{ data.users_count ?? 0 }}
                        </Button>
                    </template>
                </Column>
                <Column
                    field="created_at"
                    header="Létrehozva"
                    sortable
                    style="width: 220px"
                />

                <Column
                    header="Műveletek"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
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

        <Dialog
            v-model:visible="usersModalOpen"
            modal
            header="Role felhasználók szerkesztése"
            :style="{ width: '34rem', maxWidth: '95vw' }"
            :closable="!usersModalLoading"
            @hide="closeUsersModal"
        >
            <div class="space-y-4">
                <div>
                    <div class="font-medium">{{ usersModalRole?.name }}</div>
                    <div class="text-sm text-slate-500">
                        {{ usersModalSummary }}
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700"
                        >Felhasználók</label
                    >
                    <MultiSelect
                        v-model="usersModalSelectedIds"
                        :options="userOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Felhasználók kiválasztása"
                        class="w-full"
                        display="chip"
                        filter
                        :loading="usersModalLoading"
                        :disabled="usersModalLoading"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Mégse"
                        severity="secondary"
                        text
                        :disabled="usersModalLoading"
                        @click="closeUsersModal"
                    />
                    <Button
                        label="Mentés"
                        icon="pi pi-check"
                        :loading="usersModalLoading"
                        @click="saveUsersModal"
                    />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
