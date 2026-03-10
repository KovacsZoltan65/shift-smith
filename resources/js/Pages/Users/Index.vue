<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import { FilterMatchMode, FilterOperator } from "@primevue/core/api";
import { trans } from "laravel-vue-i18n";

import RowActionMenu from "@/Components/DataTable/RowActionMenu.vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import Dialog from "primevue/dialog";
import Select from "primevue/select";
import Tag from "primevue/tag";

import CreateModal from "@/Pages/Users/CreateModal.vue";
import EditModal from "@/Pages/Users/EditModal.vue";
import PasswordResetModal from "@/Pages/Users/PasswordResetModal.vue";
import UserService from "@/services/UserService.js";
import RoleService from "@/services/Auth/RoleService.js";
import CompanyService from "@/services/CompanyService.js";
import { csrfFetch } from "@/lib/csrfFetch";
import { usePermissions } from "@/composables/usePermissions";
import { IconField, InputIcon } from "primevue";

const page = usePage();
const { has } = usePermissions();

const props = defineProps({
    title: { type: String, default: "" },
    filter: { type: Object, default: () => ({}) },
});
const title = computed(() => trans("users.title"));

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
const selected = ref([]);
const companyOptions = ref([]);
const dt = ref(null);

const authUserId = computed(() => Number(page.props.auth?.user?.id ?? 0));
const canManageUserRoles = computed(() => has("users.assignRoles"));
const defaultCompanyId = computed(
    () => Number(page.props.companyContext?.current_company_id ?? 0) || null,
);

const globalFilterFields = ["name", "email", "primary_role_name", "guard_name"];
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
    primary_role_name: {
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
        if (Array.isArray(entry.constraints)) {
            return entry.constraints.some(
                (constraint) =>
                    constraint?.value !== null && constraint?.value !== "",
            );
        }

        return false;
    });
});

const isSelf = (row) => Number(row?.id ?? 0) === authUserId.value;

const roleLabel = (row) =>
    row?.primary_role_name ||
    (Array.isArray(row?.roles) && row.roles.length
        ? row.roles[0]?.name
        : null) ||
    "—";

const dateLocale = computed(
    () => page.props.preferences?.locale || page.props.locale || "en",
);

const roleSeverity = (roleName) => {
    if (roleName === "superadmin") return "danger";
    if (roleName === "admin") return "warning";
    if (roleName === "operator") return "info";
    return "secondary";
};

const buildRowMenuItems = (row) => [
    {
        label: trans("edit"),
        icon: "pi pi-pencil",
        command: () => openEditModal(row),
    },
    {
        label: trans("users.actions.change_role"),
        icon: "pi pi-user-edit",
        disabled: actionLoading.value || !canManageUserRoles.value,
        command: () => openRoleModal(row),
    },
    {
        label: trans("delete"),
        icon: "pi pi-trash",
        disabled: isSelf(row) || actionLoading.value,
        command: () => confirmDeleteOne(row),
    },
    {
        label: trans("users.actions.reset_password"),
        icon: "pi pi-key",
        command: () => openPasswordResetModal(row),
    },
];

const openCreate = () => {
    createOpen.value = true;
};

const onSaved = async () => {
    selected.value = [];
    await fetchUsers();
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: trans("users.messages.saved"),
        life: 2000,
    });
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
        : (response?.data?.data ?? []);

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
            roles.value.find(
                (role) => role.value === Number(row?.role_id ?? 0),
            ) ??
            null;

        selectedRoleId.value = currentRole?.value ?? null;
        roleOpen.value = true;
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("users.messages.roles_load_failed"),
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
            Number(selectedRoleId.value),
        );

        closeRoleModal();
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("users.messages.role_updated"),
            life: 2500,
        });

        await fetchUsers();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("users.messages.role_save_failed"),
            life: 3500,
        });
    } finally {
        roleLoading.value = false;
    }
};

const fetchUsers = async () => {
    loading.value = true;
    error.value = null;

    try {
        const response = await UserService.fetchUsers({
            page: 1,
            per_page: 100,
            field: "name",
            order: "asc",
        });
        const json = response?.data ?? {};

        rows.value = Array.isArray(json?.data) ? json.data : [];
    } catch (e) {
        error.value = e?.message || trans("users.messages.fetch_failed");
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const fetchCompanyOptions = async () => {
    try {
        const response = await CompanyService.getToSelect();
        const items = Array.isArray(response?.data)
            ? response.data
            : (response?.data?.data ?? []);

        companyOptions.value = items.map((company) => ({
            label: company.name,
            value: Number(company.id),
        }));
    } catch (e) {
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: e?.message || trans("users.messages.company_list_failed"),
            life: 3500,
        });
    }
};

const formatDate = (value) => {
    if (!value) return "";
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    return new Intl.DateTimeFormat(dateLocale.value, {
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
            let msg = trans("users.messages.delete_failed_http", {
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
            detail: trans("users.messages.deleted_success"),
            life: 2500,
        });

        selected.value = selected.value.filter((x) => x.id !== id);
        await fetchUsers();
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

const onPasswordResetSent = () => {
    toast.add({
        severity: "success",
        summary: trans("common.success"),
        detail: trans("users.messages.reset_sent"),
        life: 2500,
    });
};

const confirmDeleteOne = (row) => {
    confirm.require({
        message: trans("users.dialogs.delete_confirm", {
            name: `${row.name} (${row.email})`,
        }),
        header: trans("common.confirmation"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
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
            let msg = trans("users.messages.bulk_delete_failed_http", {
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
            detail: trans("users.messages.bulk_deleted_success", {
                count: ids.length,
            }),
            life: 2500,
        });

        selected.value = [];
        await fetchUsers();
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
        message: trans("users.dialogs.bulk_delete_confirm", {
            count: ids.length,
        }),
        header: trans("users.actions.bulk_delete"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("delete"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => bulkDelete(ids),
    });
};

onMounted(async () => {
    initFilters();
    await Promise.all([fetchUsers(), fetchCompanyOptions()]);
});
</script>

<template>
    <Head :title="title" />

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
    <PasswordResetModal
        v-model="pwOpen"
        :user="pwUser"
        @sent="onPasswordResetSent"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>

                    <Button
                        :label="trans('users.actions.create')"
                        icon="pi pi-plus"
                        size="small"
                        @click="openCreate"
                    />

                    <Button
                        :label="trans('users.actions.bulk_delete')"
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
                            trans("users.selected_count", {
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
                :rowSelectable="(row) => !isSelf(row)"
                v-model:filters="filters"
                :value="rows"
                dataKey="id"
                paginator
                :rowsPerPageOptions="[10, 25, 50, 100]"
                :rows="10"
                :loading="loading || actionLoading || roleLoading"
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
                            :label="trans('users.filters.clear')"
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
                                    trans('users.filters.keyword_search')
                                "
                            />
                        </IconField>
                    </div>
                </template>

                <template #empty>{{ trans("users.states.empty") }}</template>
                <template #loading>{{
                    trans("users.states.loading")
                }}</template>

                <Column
                    selectionMode="multiple"
                    headerStyle="width: 3rem"
                    :disabledSelection="(row) => isSelf(row)"
                />

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
                            :placeholder="trans('users.filters.name')"
                        />
                    </template>
                </Column>
                <Column
                    field="email"
                    filterField="email"
                    :header="trans('columns.email')"
                    filter
                    sortable
                    :showFilterMatchModes="false"
                >
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="trans('users.filters.email')"
                        />
                    </template>
                </Column>
                <Column
                    field="primary_role_name"
                    filterField="primary_role_name"
                    :header="trans('columns.role')"
                    filter
                    :showFilterMatchModes="false"
                    style="width: 180px"
                >
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
                    <template #filter="{ filterModel }">
                        <InputText
                            v-model="filterModel.value"
                            class="w-full"
                            :placeholder="trans('users.filters.role')"
                        />
                    </template>
                </Column>
                <Column
                    field="created_at"
                    :header="trans('columns.created_at')"
                    sortable
                >
                    <template #body="{ data }">
                        {{ formatDate(data.created_at) }}
                    </template>
                </Column>

                <Column
                    :header="trans('columns.actions')"
                    headerStyle="width: 3rem"
                    bodyStyle="white-space: nowrap;"
                >
                    <template #body="{ data }">
                        <div class="flex justify-end gap-2">
                            <RowActionMenu
                                :items="buildRowMenuItems(data)"
                                :disabled="actionLoading"
                                :buttonTitle="
                                    trans('users.actions.edit_title', {
                                        name: data.name,
                                    })
                                "
                            />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog
            v-model:visible="roleOpen"
            modal
            :header="trans('users.actions.role_title')"
            :style="{ width: '28rem', maxWidth: '95vw' }"
            :closable="!roleLoading"
            @hide="closeRoleModal"
        >
            <div class="space-y-4">
                <div>
                    <div class="text-sm text-slate-500">
                        {{ trans("users.dialogs.role_user") }}
                    </div>
                    <div class="font-medium">{{ roleUser?.name }}</div>
                    <div class="text-sm text-slate-500">
                        {{ roleUser?.email }}
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">{{
                        trans("columns.role")
                    }}</label>
                    <Select
                        v-model="selectedRoleId"
                        :options="roles"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('users.dialogs.role_select')"
                        class="w-full"
                        :loading="roleLoading"
                        :disabled="roleLoading"
                    />
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <Button
                        :label="trans('common.cancel')"
                        severity="secondary"
                        text
                        :disabled="roleLoading"
                        @click="closeRoleModal"
                    />
                    <Button
                        :label="trans('common.save')"
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
