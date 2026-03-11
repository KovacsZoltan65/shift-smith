<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UserAssignmentsService from "@/services/UserAssignmentsService.js";
import ErrorService from "@/services/ErrorService.js";

import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const props = defineProps({
    title: { type: String, default: "" },
});
const title = computed(() => props.title || trans("user_assignments.title"));

const toast = useToast();
const confirm = useConfirm();

const users = ref([]);
const usersMeta = ref({ current_page: 1, per_page: 15, total: 0, last_page: 1 });
const selectedUserId = ref(null);
const selectedUser = ref(null);
const search = ref("");
const companyToAttach = ref(null);

const usersLoading = ref(false);
const detailLoading = ref(false);
const actionLoading = ref(false);

const employeeDialogVisible = ref(false);
const employeeDialogCompany = ref(null);
const employeeSelection = ref(null);
const employeeErrors = ref({});

let searchTimer = null;

const selectableCompanies = computed(() =>
    Array.isArray(selectedUser.value?.selectable_companies)
        ? selectedUser.value.selectable_companies.map((company) => ({
              label: company.name,
              value: Number(company.id),
          }))
        : []
);

const selectedUserRow = computed(
    () =>
        users.value.find(
            (item) => Number(item.id) === Number(selectedUserId.value ?? 0)
        ) ?? null
);

const dialogEmployeeOptions = computed(() =>
    Array.isArray(employeeDialogCompany.value?.selectable_employees)
        ? employeeDialogCompany.value.selectable_employees.map((employee) => ({
              label: employee.email
                  ? `${employee.name} (${employee.email})`
                  : employee.name,
              value: Number(employee.id),
          }))
        : []
);

const userRoleLabel = (user) => {
    if (user?.is_superadmin) return "superadmin";
    return user?.primary_role_name ?? "—";
};

const userRoleSeverity = (user) => {
    if (user?.is_superadmin) return "contrast";
    if (!user?.primary_role_name) return "secondary";
    if (user.primary_role_name === "admin") return "warning";
    if (user.primary_role_name === "manager") return "info";
    return "secondary";
};

const parseMessage = async (error, fallback) => {
    const detail =
        error?.response?.data?.message ||
        error?.normalizedErrors?.user_id?.[0] ||
        error?.normalizedErrors?.company_id?.[0] ||
        error?.normalizedErrors?.employee_id?.[0];

    return detail || error?.message || fallback;
};

const resetDialogState = () => {
    employeeDialogCompany.value = null;
    employeeSelection.value = null;
    employeeErrors.value = {};
    employeeDialogVisible.value = false;
};

const applyUserPayload = (payload) => {
    selectedUser.value = payload ?? null;
    selectedUserId.value = payload?.user_id ? Number(payload.user_id) : null;
    companyToAttach.value = null;

    if (employeeDialogCompany.value) {
        const nextCompany =
            payload?.companies?.find(
                (company) => Number(company.id) === Number(employeeDialogCompany.value.id)
            ) ?? null;

        if (!nextCompany) {
            resetDialogState();
            return;
        }

        employeeDialogCompany.value = nextCompany;
        employeeSelection.value = Number(nextCompany.assigned_employee?.id ?? 0) || null;
    }
};

const fetchUsers = async () => {
    usersLoading.value = true;

    try {
        const response = await UserAssignmentsService.fetchUsers({
            q: search.value?.trim() || "",
            per_page: usersMeta.value.per_page,
        });

        users.value = Array.isArray(response?.data?.data) ? response.data.data : [];
        usersMeta.value = response?.data?.meta ?? usersMeta.value;

        if (!selectedUserId.value && users.value.length > 0) {
            await selectUser(users.value[0]);
        }
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_assignments_users_fetch_error",
            priority: "medium",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.users_fetch_failed")),
            life: 3500,
        });
    } finally {
        usersLoading.value = false;
    }
};

const fetchUser = async (userId) => {
    if (!userId) return;

    detailLoading.value = true;

    try {
        const response = await UserAssignmentsService.fetchUser(userId);
        applyUserPayload(response?.data?.data ?? null);
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_assignments_user_fetch_error",
            priority: "medium",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.user_fetch_failed")),
            life: 3500,
        });
    } finally {
        detailLoading.value = false;
    }
};

const selectUser = async (user) => {
    const nextId = Number(user?.id ?? 0);
    if (!nextId || nextId === Number(selectedUserId.value ?? 0)) return;

    selectedUserId.value = nextId;
    await fetchUser(nextId);
};

const refreshCurrentUser = async () => {
    if (selectedUserId.value) {
        await fetchUser(selectedUserId.value);
    }
};

const attachCompany = async () => {
    if (!selectedUserId.value || !companyToAttach.value) return;

    actionLoading.value = true;

    try {
        const response = await UserAssignmentsService.attachCompany(
            selectedUserId.value,
            {
                company_id: Number(companyToAttach.value),
            }
        );

        applyUserPayload(response?.data?.data ?? null);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_assignments.messages.company_attached"),
            life: 2500,
        });
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_assignments_company_attach_error",
            priority: "high",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.company_attach_failed")),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const detachCompany = async (company) => {
    actionLoading.value = true;

    try {
        const response = await UserAssignmentsService.detachCompany(
            selectedUserId.value,
            company.id
        );
        applyUserPayload(response?.data?.data ?? null);
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_assignments.messages.company_detached"),
            life: 2500,
        });
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_assignments_company_detach_error",
            priority: "high",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.company_detach_failed")),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const openEmployeeDialog = (company) => {
    employeeDialogCompany.value = company;
    employeeSelection.value = Number(company?.assigned_employee?.id ?? 0) || null;
    employeeErrors.value = {};
    employeeDialogVisible.value = true;
};

const saveEmployeeAssignment = async () => {
    if (
        !selectedUserId.value ||
        !employeeDialogCompany.value?.id ||
        !employeeSelection.value
    )
        return;

    actionLoading.value = true;
    employeeErrors.value = {};

    try {
        const response = await UserAssignmentsService.assignEmployee(
            selectedUserId.value,
            employeeDialogCompany.value.id,
            {
                employee_id: Number(employeeSelection.value),
            }
        );

        applyUserPayload(response?.data?.data ?? null);
        employeeDialogVisible.value = false;
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_assignments.messages.employee_assigned"),
            life: 2500,
        });
    } catch (error) {
        employeeErrors.value = UserAssignmentsService.extractErrors(error) ?? {};
        await ErrorService.logClientError(error, {
            category: "user_assignments_employee_assign_error",
            priority: "high",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.employee_assign_failed")),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const removeEmployeeAssignment = async (company) => {
    actionLoading.value = true;

    try {
        const response = await UserAssignmentsService.removeEmployee(
            selectedUserId.value,
            company.id
        );
        applyUserPayload(response?.data?.data ?? null);
        if (employeeDialogCompany.value?.id === company.id) {
            employeeSelection.value = null;
            employeeErrors.value = {};
        }
        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_assignments.messages.employee_removed"),
            life: 2500,
        });
    } catch (error) {
        employeeErrors.value = UserAssignmentsService.extractErrors(error) ?? {};
        await ErrorService.logClientError(error, {
            category: "user_assignments_employee_remove_error",
            priority: "high",
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: await parseMessage(error, trans("user_assignments.messages.employee_remove_failed")),
            life: 3500,
        });
    } finally {
        actionLoading.value = false;
    }
};

const confirmDetachCompany = (company) => {
    confirm.require({
        message: trans("user_assignments.dialogs.detach_company_confirm", { name: company.name }),
        header: trans("user_assignments.dialogs.detach_company_title"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("user_assignments.actions.remove"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => detachCompany(company),
    });
};

const confirmRemoveEmployee = (company) => {
    confirm.require({
        message: trans("user_assignments.dialogs.remove_employee_confirm", { name: company.name }),
        header: trans("user_assignments.dialogs.remove_employee_title"),
        icon: "pi pi-exclamation-triangle",
        acceptLabel: trans("user_assignments.actions.remove"),
        rejectLabel: trans("common.cancel"),
        acceptClass: "p-button-danger",
        accept: () => removeEmployeeAssignment(company),
    });
};

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        fetchUsers();
    }, 300);
});

watch(employeeDialogVisible, (visible) => {
    if (!visible) {
        resetDialogState();
    }
});

onMounted(async () => {
    await fetchUsers();
});
</script>

<template>
    <Head :title="title" />
    <Toast />
    <ConfirmDialog />

    <AuthenticatedLayout>
        <div class="p-6">
            <div
                class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">{{ title }}</h1>
                    <p class="text-sm text-surface-500">
                        {{ trans("user_assignments.description") }}
                    </p>
                </div>
                <Button
                    icon="pi pi-refresh"
                    :label="trans('user_assignments.actions.refresh')"
                    severity="secondary"
                    :loading="usersLoading || detailLoading"
                    :disabled="actionLoading"
                    @click="selectedUserId ? refreshCurrentUser() : fetchUsers()"
                />
            </div>

            <div class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
                <section
                    class="rounded-xl border border-surface-200 bg-white p-4 shadow-sm"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">{{ trans("user_assignments.sections.users") }}</h2>
                        <Tag :value="String(usersMeta.total ?? 0)" severity="secondary" />
                    </div>

                    <InputText
                        v-model="search"
                        class="mb-3 w-full"
                        :placeholder="trans('user_assignments.filters.search')"
                    />

                    <DataTable
                        :value="users"
                        dataKey="id"
                        scrollable
                        scrollHeight="60vh"
                        :loading="usersLoading"
                        selectionMode="single"
                        :selection="selectedUserRow"
                        @row-click="(event) => selectUser(event.data)"
                    >
                        <template #empty>{{ trans("user_assignments.states.users_empty") }}</template>

                        <Column field="name" :header="trans('columns.name')">
                            <template #body="{ data }">
                                <div class="flex flex-col">
                                    <span class="font-medium">{{ data.name }}</span>
                                    <span class="text-xs text-surface-500">{{
                                        data.email
                                    }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column :header="trans('columns.role')" style="width: 140px">
                            <template #body="{ data }">
                                <Tag
                                    :value="userRoleLabel(data)"
                                    :severity="userRoleSeverity(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </section>

                <section
                    class="rounded-xl border border-surface-200 bg-white p-4 shadow-sm"
                >
                    <div
                        v-if="!selectedUser"
                        class="flex min-h-[240px] items-center justify-center text-surface-500"
                    >
                        {{ trans("user_assignments.states.no_user_selected") }}
                    </div>

                    <template v-else>
                        <div
                            class="mb-4 flex flex-col gap-3 border-b border-surface-100 pb-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div>
                                <h2 class="text-lg font-semibold">
                                    {{ selectedUser.user_name }}
                                </h2>
                                <p class="text-sm text-surface-500">
                                    {{ trans("user_assignments.fields.user_id") }}: {{ selectedUser.user_id }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <Tag
                                    v-if="selectedUser.is_superadmin"
                                    :value="trans('user_assignments.roles.superadmin')"
                                    severity="contrast"
                                />
                                <Tag v-else :value="trans('user_assignments.roles.normal_user')" severity="secondary" />
                            </div>
                        </div>

                        <Message
                            v-if="selectedUser.read_only"
                            severity="info"
                            class="mb-4"
                        >
                            {{ selectedUser.read_only_reason }}
                        </Message>

                        <div
                            class="mb-6 rounded-xl border border-surface-100 bg-surface-50 p-4"
                        >
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-base font-semibold">
                                    {{ trans("user_assignments.sections.company_assignment") }}
                                </h3>
                            </div>

                            <div class="flex flex-col gap-3 md:flex-row">
                                <Select
                                    v-model="companyToAttach"
                                    :options="selectableCompanies"
                                    optionLabel="label"
                                    optionValue="value"
                                    :placeholder="trans('user_assignments.placeholders.select_company')"
                                    class="md:flex-1"
                                    :disabled="
                                        selectedUser.read_only ||
                                        actionLoading ||
                                        detailLoading ||
                                        !selectableCompanies.length
                                    "
                                />
                                <Button
                                    :label="trans('user_assignments.actions.attach_company')"
                                    icon="pi pi-plus"
                                    :loading="actionLoading"
                                    :disabled="
                                        selectedUser.read_only ||
                                        !companyToAttach ||
                                        detailLoading
                                    "
                                    @click="attachCompany"
                                />
                            </div>
                        </div>

                        <DataTable
                            :value="selectedUser.companies ?? []"
                            dataKey="id"
                            :loading="detailLoading || actionLoading"
                        >
                            <template #empty>{{ trans("user_assignments.states.companies_empty") }}</template>

                            <Column field="name" :header="trans('columns.company')" />

                            <Column :header="trans('user_assignments.fields.assigned_employee')">
                                <template #body="{ data }">
                                    <div
                                        v-if="data.assigned_employee"
                                        class="flex flex-col"
                                    >
                                        <span class="font-medium">{{
                                            data.assigned_employee.name
                                        }}</span>
                                        <span class="text-xs text-surface-500">{{
                                            data.assigned_employee.email || "—"
                                        }}</span>
                                    </div>
                                    <span v-else class="text-surface-500">—</span>
                                </template>
                            </Column>

                            <Column :header="trans('columns.actions')" style="width: 220px">
                                <template #body="{ data }">
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            :label="trans('user_assignments.actions.assign_employee')"
                                            icon="pi pi-user-edit"
                                            severity="secondary"
                                            size="small"
                                            :disabled="
                                                selectedUser.read_only ||
                                                actionLoading ||
                                                detailLoading
                                            "
                                            @click="openEmployeeDialog(data)"
                                        />
                                        <Button
                                            icon="pi pi-user-minus"
                                            severity="warning"
                                            text
                                            rounded
                                            :disabled="
                                                selectedUser.read_only ||
                                                !data.assigned_employee ||
                                                actionLoading ||
                                                detailLoading
                                            "
                                            @click="confirmRemoveEmployee(data)"
                                        />
                                        <Button
                                            icon="pi pi-trash"
                                            severity="danger"
                                            text
                                            rounded
                                            :disabled="
                                                selectedUser.read_only ||
                                                actionLoading ||
                                                detailLoading
                                            "
                                            @click="confirmDetachCompany(data)"
                                        />
                                    </div>
                                </template>
                            </Column>
                        </DataTable>
                    </template>
                </section>
            </div>
        </div>

        <Dialog
            v-model:visible="employeeDialogVisible"
            modal
            :header="trans('user_assignments.dialogs.employee_assignment_title')"
            :style="{ width: '32rem' }"
            @hide="resetDialogState"
        >
            <div class="flex flex-col gap-4">
                <div
                    v-if="employeeDialogCompany"
                    class="rounded border border-surface-200 bg-surface-50 p-3 text-sm"
                >
                    <div class="font-medium">{{ employeeDialogCompany.name }}</div>
                    <div class="text-surface-500">
                        {{ trans("user_assignments.fields.current") }}:
                        {{ employeeDialogCompany.assigned_employee?.name || "—" }}
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-medium">{{ trans("user_assignments.fields.selectable_employee") }}</label>
                    <Select
                        v-model="employeeSelection"
                        :options="dialogEmployeeOptions"
                        optionLabel="label"
                        optionValue="value"
                        :placeholder="trans('user_assignments.placeholders.select_employee')"
                        :disabled="actionLoading"
                    />
                </div>

                <Message v-if="employeeErrors.employee_id?.[0]" severity="error">
                    {{ employeeErrors.employee_id[0] }}
                </Message>
                <Message v-if="employeeErrors.company_id?.[0]" severity="error">
                    {{ employeeErrors.company_id[0] }}
                </Message>
                <Message v-if="employeeErrors.user_id?.[0]" severity="error">
                    {{ employeeErrors.user_id[0] }}
                </Message>
            </div>

            <template #footer>
                <div class="flex w-full justify-between gap-2">
                    <Button
                        :label="trans('user_assignments.actions.remove')"
                        icon="pi pi-trash"
                        severity="danger"
                        text
                        :disabled="
                            !employeeDialogCompany?.assigned_employee || actionLoading
                        "
                        @click="removeEmployeeAssignment(employeeDialogCompany)"
                    />
                    <div class="flex gap-2">
                        <Button
                            :label="trans('common.cancel')"
                            severity="secondary"
                            text
                            @click="employeeDialogVisible = false"
                        />
                        <Button
                            :label="trans('common.save')"
                            icon="pi pi-check"
                            :loading="actionLoading"
                            :disabled="!employeeSelection"
                            @click="saveEmployeeAssignment"
                        />
                    </div>
                </div>
            </template>
        </Dialog>
    </AuthenticatedLayout>
</template>
