<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { csrfFetch } from "@/lib/csrfFetch";
import ErrorService from "@/services/ErrorService.js";

import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import ConfirmDialog from "primevue/confirmdialog";
import { useConfirm } from "primevue/useconfirm";
import DataTable from "primevue/datatable";
import Column from "primevue/column";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Tag from "primevue/tag";
import { Select } from "primevue";

const props = defineProps({
    title: { type: String, default: "" },
    users: { type: Array, default: () => [] },
    selected_user_id: { type: Number, default: null },
    current_mapping: { type: Array, default: () => [] },
    selectable_employees: { type: Array, default: () => [] },
});
const title = computed(() => props.title || trans("user_employees.title"));

const toast = useToast();
const confirm = useConfirm();

const users = ref(Array.isArray(props.users) ? props.users : []);
const selectedUserId = ref(
    Number(props.selected_user_id ?? users.value?.[0]?.id ?? 0) || null
);
const currentMapping = ref(
    Array.isArray(props.current_mapping) ? props.current_mapping : []
);
const selectableEmployees = ref(
    Array.isArray(props.selectable_employees) ? props.selectable_employees : []
);
const selectedEmployeeId = ref(null);

const loading = ref(false);
const actionLoading = ref(false);
const search = ref("");

const filteredUsers = computed(() => {
    const q = String(search.value ?? "")
        .trim()
        .toLowerCase();
    if (!q) return users.value;

    return users.value.filter((item) => {
        const name = String(item?.name ?? "").toLowerCase();
        const email = String(item?.email ?? "").toLowerCase();
        return name.includes(q) || email.includes(q);
    });
});

const selectedUser = computed(() => {
    if (!selectedUserId.value) return null;
    return (
        users.value.find((user) => Number(user.id) === Number(selectedUserId.value)) ??
        null
    );
});

const selectableOptions = computed(() =>
    selectableEmployees.value.map((employee) => ({
        label: employee?.name ?? `#${employee?.id ?? "?"}`,
        value: Number(employee?.id ?? 0),
    }))
);

const companyLabel = (employee) => {
    const names = Array.isArray(employee?.companies)
        ? employee.companies.map((company) => company.name).filter(Boolean)
        : [];

    return names.length ? names.join(", ") : "-";
};

const parseError = async (error, fallback = trans("common.unknown_error")) => {
    if (error instanceof Error) {
        return error.message || fallback;
    }

    return fallback;
};

const fetchMapping = async (userId, keepSelectedEmployee = false) => {
    if (!userId) return;

    loading.value = true;
    try {
        const response = await fetch(`/admin/user-employees/${userId}/employees`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!response.ok) {
            let message = trans("user_employees.messages.fetch_failed_http", {
                status: response.status,
            });
            try {
                const body = await response.json();
                message = body?.message || message;
            } catch (_) {}
            throw new Error(message);
        }

        const body = await response.json();
        currentMapping.value = body?.data?.employees ?? [];
        selectableEmployees.value = body?.data?.selectable_employees ?? [];

        if (!keepSelectedEmployee) {
            selectedEmployeeId.value = null;
        }
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_employee_fetch_error",
            priority: "medium",
        });

        const message = await parseError(
            error,
            trans("user_employees.messages.fetch_failed")
        );
        toast.add({ severity: "error", summary: trans("common.error"), detail: message, life: 3500 });
    } finally {
        loading.value = false;
    }
};

const selectUser = async (user) => {
    const userId = Number(user?.id ?? 0);
    if (!userId || userId === Number(selectedUserId.value ?? 0)) return;
    selectedUserId.value = userId;
    await fetchMapping(userId);
};

const refresh = async () => {
    await fetchMapping(Number(selectedUserId.value ?? 0), true);
};

const addEmployee = async () => {
    const userId = Number(selectedUserId.value ?? 0);
    const employeeId = Number(selectedEmployeeId.value ?? 0);
    if (!userId || !employeeId) return;

    actionLoading.value = true;
    try {
        const response = await csrfFetch(`/admin/user-employees/${userId}/employees`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify({ employee_id: employeeId }),
        });

        if (!response.ok) {
            let message = trans("user_employees.messages.save_failed_http", {
                status: response.status,
            });
            try {
                const body = await response.json();
                if (body?.errors?.employee_id?.[0]) {
                    message = body.errors.employee_id[0];
                } else {
                    message = body?.message || message;
                }
            } catch (_) {}
            throw new Error(message);
        }

        const body = await response.json();
        currentMapping.value = body?.data?.employees ?? [];
        selectableEmployees.value = body?.data?.selectable_employees ?? [];
        selectedEmployeeId.value = null;

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_employees.messages.attach_success"),
            life: 2200,
        });
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_employee_attach_error",
            priority: "high",
        });

        const message = await parseError(error, trans("user_employees.messages.save_failed"));
        toast.add({ severity: "error", summary: trans("common.error"), detail: message, life: 3500 });
    } finally {
        actionLoading.value = false;
    }
};

const removeEmployee = async (employee) => {
    const userId = Number(selectedUserId.value ?? 0);
    const employeeId = Number(employee?.id ?? 0);
    if (!userId || !employeeId) return;

    actionLoading.value = true;
    try {
        const response = await csrfFetch(
            `/admin/user-employees/${userId}/employees/${employeeId}`,
            {
                method: "DELETE",
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            }
        );

        if (!response.ok) {
            let message = trans("user_employees.messages.remove_failed_http", {
                status: response.status,
            });
            try {
                const body = await response.json();
                message = body?.message || message;
            } catch (_) {}
            throw new Error(message);
        }

        const body = await response.json();
        currentMapping.value = body?.data?.employees ?? [];
        selectableEmployees.value = body?.data?.selectable_employees ?? [];

        toast.add({
            severity: "success",
            summary: trans("common.success"),
            detail: trans("user_employees.messages.detach_success"),
            life: 2200,
        });
    } catch (error) {
        await ErrorService.logClientError(error, {
            category: "user_employee_detach_error",
            priority: "high",
        });

        const message = await parseError(error, trans("user_employees.messages.remove_failed"));
        toast.add({ severity: "error", summary: trans("common.error"), detail: message, life: 3500 });
    } finally {
        actionLoading.value = false;
    }
};

const confirmRemove = (employee) => {
    confirm.require({
        message: trans("user_employees.dialogs.remove_confirm", {
            name: employee?.name ?? trans("user_employees.messages.unknown_employee"),
        }),
        header: trans("user_employees.dialogs.remove_title"),
        icon: "pi pi-exclamation-triangle",
        rejectLabel: trans("common.cancel"),
        acceptLabel: trans("user_assignments.actions.remove"),
        acceptClass: "p-button-danger",
        accept: () => removeEmployee(employee),
    });
};
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
                <h1 class="text-2xl font-semibold">{{ title }}</h1>
                <Button
                    icon="pi pi-refresh"
                    :label="trans('user_employees.actions.refresh')"
                    severity="secondary"
                    size="small"
                    :loading="loading"
                    :disabled="!selectedUserId || actionLoading"
                    @click="refresh"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <section class="rounded border border-surface-200 bg-white p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">{{ trans("user_employees.sections.users") }}</h2>
                        <InputText
                            v-model="search"
                            :placeholder="trans('user_employees.placeholders.search_users')"
                            class="w-full max-w-64"
                        />
                    </div>

                    <DataTable
                        :value="filteredUsers"
                        dataKey="id"
                        selectionMode="single"
                        :selection="selectedUser"
                        :loading="loading"
                        @row-click="(event) => selectUser(event.data)"
                    >
                        <template #empty>{{ trans("user_employees.states.no_users") }}</template>

                        <Column field="name" :header="trans('columns.name')" />
                        <Column field="email" :header="trans('columns.email')" />
                    </DataTable>
                </section>

                <section class="rounded border border-surface-200 bg-white p-4">
                    <div class="mb-3">
                        <h2 class="text-lg font-semibold">
                            {{ trans("user_employees.sections.assignments") }}
                            <span
                                v-if="selectedUser"
                                class="text-sm font-normal text-surface-500"
                            >
                                - {{ selectedUser.name }}
                            </span>
                        </h2>
                    </div>

                    <div
                        v-if="selectedUserId"
                        class="mb-4 flex flex-col gap-2 md:flex-row md:items-end"
                    >
                        <div class="w-full md:flex-1">
                            <label class="mb-1 block text-sm text-surface-600"
                                >{{ trans("user_employees.fields.assignable_employee") }}</label
                            >
                            <Select
                                v-model="selectedEmployeeId"
                                :options="selectableOptions"
                                optionLabel="label"
                                optionValue="value"
                                :placeholder="trans('user_employees.placeholders.select_employee')"
                                class="w-full"
                                :disabled="
                                    actionLoading || loading || !selectableOptions.length
                                "
                            />
                        </div>
                        <Button
                            :label="trans('user_employees.actions.add')"
                            icon="pi pi-plus"
                            :disabled="!selectedEmployeeId || actionLoading || loading"
                            :loading="actionLoading"
                            @click="addEmployee"
                        />
                    </div>

                    <div
                        v-if="selectedUserId && !selectableOptions.length"
                        class="mb-4 rounded border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800"
                    >
                        {{ trans("user_employees.states.no_selectable_employees") }}
                    </div>

                    <DataTable
                        :value="currentMapping"
                        dataKey="id"
                        :loading="loading || actionLoading"
                    >
                        <template #empty>{{ trans("user_employees.states.no_mappings") }}</template>

                        <Column field="name" :header="trans('columns.employee')" />
                        <Column :header="trans('columns.email')">
                            <template #body="{ data }">
                                {{ data.email || "-" }}
                            </template>
                        </Column>
                        <Column :header="trans('columns.companies')">
                            <template #body="{ data }">
                                <Tag :value="companyLabel(data)" />
                            </template>
                        </Column>
                        <Column :header="trans('columns.actions')" style="width: 1%">
                            <template #body="{ data }">
                                <Button
                                    icon="pi pi-trash"
                                    severity="danger"
                                    text
                                    rounded
                                    :disabled="actionLoading || loading"
                                    @click="confirmRemove(data)"
                                />
                            </template>
                        </Column>
                    </DataTable>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
