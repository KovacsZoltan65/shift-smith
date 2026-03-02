<script setup>
import { computed, onMounted, ref } from "vue";
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
import Dialog from "primevue/dialog";
import Dropdown from "primevue/dropdown";
import Tag from "primevue/tag";

import CreateModal from "@/Pages/Users/CreateModal.vue";
import EditModal from "@/Pages/Users/EditModal.vue";
import PasswordResetModal from "@/Pages/Users/PasswordResetModal.vue";
import UserService from "@/services/UserService.js";
import RoleService from "@/services/Auth/RoleService.js";
import CompanyService from "@/services/CompanyService.js";
import { csrfFetch } from "@/lib/csrfFetch";
import { usePermissions } from "@/composables/usePermissions";

const page = usePage();
const { has } = usePermissions();

const props = defineProps({
    title: String,
    filter: Object,
});

const createOpen = ref(false);
const editOpen = ref(false);
const pwOpen = ref(false);
const roleOpen = ref(false);
const editUser = ref(null);
const pwUser = ref(null);
const roleUser = ref(null);
const selectedRoleId = ref(null);
const roles = ref([]);

const toast = useToast();
const confirm = useConfirm();

const loading = ref(false);
const actionLoading = ref(false);
const roleLoading = ref(false);
const error = ref(null);

const rows = ref([]);
const totalRecords = ref(0);
const selected = ref([]);
const companyOptions = ref([]);

const authUserId = computed(() => Number(page.props.auth?.user?.id ?? 0));
const canManageUserRoles = computed(() => has("users.assignRoles"));
const defaultCompanyId = computed(
    () => Number(page.props.companyContext?.current_company_id ?? 0) || null
);

const rowMenu = ref();
const rowMenuModel = ref([]);

const lazy = ref({
    first: 0,
    rows: 10,
    page: 0,
    sortField: "name",
    sortOrder: 1,
});

const search = ref(props.filter?.search ?? "");
let t = null;

const isSelf = (row) => Number(row?.id ?? 0) === authUserId.value;

const roleLabel = (row) =>
    row?.primary_role_name ||
    (Array.isArray(row?.roles) && row.roles.length ? row.roles[0]?.name : null) ||
    "—";

const roleSeverity = (roleName) => {
    if (roleName === "superadmin") return "danger";
    if (roleName === "admin") return "warning";
    if (roleName === "operator") return "info";
    return "secondary";
};

const openRowMenu = (event, row) => {
    rowMenuModel.value = [
        {
            label: "Szerkesztés",
            icon: "pi pi-pencil",
            command: () => openEditModal(row),
        },
        {
            label: "Szerepkör",
            icon: "pi pi-user-edit",
            disabled: actionLoading.value || !canManageUserRoles.value,
            command: () => openRoleModal(row),
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

const ensureRolesLoaded = async () => {
    if (roles.value.length) return;

    const response = await RoleService.getToSelect();
    const items = Array.isArray(response?.data)
        ? response.data
        : response?.data?.data ?? [];

    roles.value = items.map((role) => ({
        label: role.name,
        value: Number(role.id),
        name: role.name,
    }));
};

const openRoleModal = async (row) => {
    if (!canManageUserRoles.value) return;

    roleLoading.value = true;

    try {
        await ensureRolesLoaded();
        roleUser.value = row;

        const currentRole =
            roles.value.find((role) => role.name === roleLabel(row)) ??
            roles.value.find((role) => role.value === Number(row?.role_id ?? 0)) ??
            null;

        selectedRoleId.value = currentRole?.value ?? null;
        roleOpen.value = true;
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Nem sikerült a szerepkörök betöltése.",
            life: 3500,
        });
    } finally {
        roleLoading.value = false;
    }
};

const closeRoleModal = () => {
    roleOpen.value = false;
    roleUser.value = null;
    selectedRoleId.value = null;
};

const saveRole = async () => {
    if (!roleUser.value?.id || !selectedRoleId.value) return;

    roleLoading.value = true;

    try {
        await UserService.updatePrimaryRole(
            Number(roleUser.value.id),
            Number(selectedRoleId.value)
        );

        closeRoleModal();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: "Szerepkör frissítve.",
            life: 2500,
        });

        await fetchUsers();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "A szerepkör mentése sikertelen.",
            life: 3500,
        });
    } finally {
        roleLoading.value = false;
    }
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

    return q;
};

const fetchUsers = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await UserService.fetchUsers(buildQuery());
        const json = response?.data ?? {};

        rows.value = Array.isArray(json?.data) ? json.data : [];
        totalRecords.value = json?.meta?.total ?? 0;
    } catch (e) {
        error.value = e?.message || "Ismeretlen hiba";
        rows.value = [];
        totalRecords.value = 0;
    } finally {
        loading.value = false;
    }
};

const fetchCompanyOptions = async () => {
    try {
        const response = await CompanyService.getToSelect();
        const items = Array.isArray(response?.data)
            ? response.data
            : response?.data?.data ?? [];

        companyOptions.value = items.map((company) => ({
            label: company.name,
            value: Number(company.id),
        }));
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "A céglista betöltése sikertelen.",
            life: 3500,
        });
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
        const res = await csrfFetch(`/users/destroy_bulk`, {
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
    router.visit(`/users/${row.id}/edit`);
};

onMounted(async () => {
    await Promise.all([fetchUsers(), fetchCompanyOptions()]);
});
</script>

<template>
    <Head title="Felhasználók" />

    <Toast />
    <ConfirmDialog />

    <CreateModal
        v-model="createOpen"
        :companies="companyOptions"
        :defaultCompanyId="defaultCompanyId"
        @saved="onSaved"
    />
    <EditModal
        v-model="editOpen"
        :user="editUser"
        :companies="companyOptions"
        @saved="onSaved"
    />
    <PasswordResetModal v-model="pwOpen" :user="pwUser" @sent="onPasswordResetSent" />

    <AuthenticatedLayout>
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
                :loading="loading || actionLoading || roleLoading"
                sortMode="single"
                :sortField="lazy.sortField"
                :sortOrder="lazy.sortOrder"
                @page="onPage"
                @sort="onSort"
            >
                <template #empty> Nincs találat. </template>

                <Column
                    selectionMode="multiple"
                    headerStyle="width: 3rem"
                    :disabledSelection="(row) => isSelf(row)"
                />

                <Column field="id" header="ID" sortable style="width: 90px" />
                <Column field="name" header="Név" sortable />
                <Column field="email" header="Email" sortable />
                <Column field="primary_role_name" header="Role" style="width: 180px">
                    <template #body="{ data }">
                        <button
                            type="button"
                            class="inline-flex items-center"
                            :class="
                                canManageUserRoles
                                    ? 'cursor-pointer rounded hover:bg-slate-100'
                                    : 'cursor-default'
                            "
                            :disabled="!canManageUserRoles"
                            @click="openRoleModal(data)"
                        >
                            <Tag
                                :value="roleLabel(data)"
                                :severity="roleSeverity(roleLabel(data))"
                            />
                        </button>
                    </template>
                </Column>
                <Column field="created_at" header="Létrehozva" sortable>
                    <template #body="{ data }">
                        {{ formatDate(data.created_at) }}
                    </template>
                </Column>

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
            v-model:visible="roleOpen"
            modal
            header="Szerepkör módosítása"
            :style="{ width: '28rem', maxWidth: '95vw' }"
            :closable="!roleLoading"
            @hide="closeRoleModal"
        >
            <div class="space-y-4">
                <div>
                    <div class="text-sm text-slate-500">Felhasználó</div>
                    <div class="font-medium">{{ roleUser?.name }}</div>
                    <div class="text-sm text-slate-500">{{ roleUser?.email }}</div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Role</label>
                    <Select
                        v-model="selectedRoleId"
                        :options="roles"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Szerepkör kiválasztása"
                        class="w-full"
                        :loading="roleLoading"
                        :disabled="roleLoading"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        label="Mégse"
                        severity="secondary"
                        text
                        :disabled="roleLoading"
                        @click="closeRoleModal"
                    />
                    <Button
                        label="Mentés"
                        icon="pi pi-check"
                        :loading="roleLoading"
                        :disabled="!selectedRoleId"
                        @click="saveRole"
                    />
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
