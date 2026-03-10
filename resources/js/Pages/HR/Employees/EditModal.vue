<script setup>
import { ref, watch, computed } from "vue";
import { trans } from "laravel-vue-i18n";

import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Divider from "primevue/divider";
import DatePicker from "primevue/datepicker";
import Message from "primevue/message";
import { useToast } from "primevue/usetoast";

import EmployeeFields from "@/Pages/HR/Employees/Partials/EmployeeFields.vue";
import SupervisorSelector from "@/Components/Selectors/SupervisorSelector.vue";
import LeaveProfileFields from "@/Pages/HR/Employees/Partials/LeaveProfileFields.vue";
import { csrfFetch } from "@/lib/csrfFetch";
import EmployeeLeaveProfileService from "@/services/EmployeeLeaveProfileService.js";
import ErrorService from "@/services/ErrorService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    lockCompany: { type: Boolean, default: false }, // ha később kell
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);
const toast = useToast();
const entitlementYear = new Date().getFullYear();

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const saving = ref(false);
const profileLoading = ref(false);
const entitlementLoading = ref(false);
const errors = ref({});
const profileErrors = ref({});
const entitlement = ref(null);
const supervisorHistory = ref([]);
const supervisorPreviewLoading = ref(false);
const supervisorPreview = ref(null);
let supervisorPreviewTimer = null;

const form = ref({
    company_id: null,
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    position_id: null,
    birth_date: null,
    hired_at: null,
    active: true,
    supervisor_employee_id: null,
    supervisor_valid_from: null,
});

const profileForm = ref({
    children_count: 0,
    disabled_children_count: 0,
    is_disabled: false,
});

const reset = () => {
    if (supervisorPreviewTimer !== null) {
        clearTimeout(supervisorPreviewTimer);
        supervisorPreviewTimer = null;
    }

    errors.value = {};
    profileErrors.value = {};
    saving.value = false;
    profileLoading.value = false;
    entitlementLoading.value = false;
    entitlement.value = null;
    supervisorHistory.value = [];
    supervisorPreviewLoading.value = false;
    supervisorPreview.value = null;
    form.value = {
        company_id: null,
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        position_id: null,
        birth_date: null,
        hired_at: null,
        active: true,
        supervisor_employee_id: null,
        supervisor_valid_from: null,
    };
    profileForm.value = {
        children_count: 0,
        disabled_children_count: 0,
        is_disabled: false,
    };
};

const toYmd = (value) => {
    if (!value) return null;
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return value;
    }

    const date = value instanceof Date ? value : new Date(value);
    return isNaN(date.getTime()) ? null : date.toISOString().slice(0, 10);
};

const parseDate = (val) => {
    if (!val) return null;
    if (val instanceof Date) return val;
    if (typeof val === "string" && /^\d{4}-\d{2}-\d{2}$/.test(val)) {
        const [y, m, d] = val.split("-").map(Number);
        return new Date(y, m - 1, d);
    }
    const d = new Date(val);
    return isNaN(d.getTime()) ? null : d;
};

const fillFromEmployee = (emp) => {
    if (!emp) return;

    form.value = {
        company_id: emp.company_id ?? null,
        first_name: emp.first_name ?? "",
        last_name: emp.last_name ?? "",
        email: emp.email ?? "",
        phone: emp.phone ?? "",
        position_id: emp.position_id ?? null,
        birth_date: parseDate(emp.birth_date ?? null),
        hired_at: parseDate(emp.hired_at),
        active: emp.active ?? true,
        supervisor_employee_id: null,
        supervisor_valid_from: parseDate(emp.hired_at),
    };

    supervisorHistory.value = Array.isArray(emp.supervisor_history)
        ? emp.supervisor_history
        : [];
};

const fillProfile = (profile) => {
    profileForm.value = {
        children_count: Number(profile?.children_count ?? 0),
        disabled_children_count: Number(profile?.disabled_children_count ?? 0),
        is_disabled: !!profile?.is_disabled,
    };
};

const loadLeaveProfile = async (employeeId) => {
    profileLoading.value = true;
    profileErrors.value = {};

    try {
        const response = await EmployeeLeaveProfileService.getProfile(employeeId);
        fillProfile(response?.data?.data ?? {});
    } catch (error) {
        const message = error?.response?.data?.message || trans("employees.messages.leave_profile_load_failed");
        profileErrors.value = { _general: message };
        await ErrorService.logClientError(error, {
            category: "employee_leave_profile_load",
            data: { employee_id: employeeId },
        });
    } finally {
        profileLoading.value = false;
    }
};

const loadEntitlement = async (employeeId) => {
    entitlementLoading.value = true;

    try {
        const response = await EmployeeLeaveProfileService.getEntitlement(employeeId);
        entitlement.value = response?.data?.data ?? null;
    } catch (error) {
        entitlement.value = null;
        await ErrorService.logClientError(error, {
            category: "employee_leave_entitlement_load",
            data: { employee_id: employeeId, year: entitlementYear },
        });
        toast.add({
            severity: "error",
            summary: trans("common.error"),
            detail: error?.response?.data?.message || trans("employees.messages.leave_entitlement_load_failed"),
            life: 3500,
        });
    } finally {
        entitlementLoading.value = false;
    }
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            errors.value = {};
            fillFromEmployee(props.employee);
            if (props.employee?.id) {
                loadEmployeeDetails(props.employee.id);
                loadLeaveProfile(props.employee.id);
                loadEntitlement(props.employee.id);
            }
            queueSupervisorPreview();
        } else {
            reset();
        }
    }
);

watch(
    () => props.employee,
    (emp) => {
        if (props.modelValue) {
            errors.value = {};
            fillFromEmployee(emp);
            if (emp?.id) {
                loadEmployeeDetails(emp.id);
                loadLeaveProfile(emp.id);
                loadEntitlement(emp.id);
            }
            queueSupervisorPreview();
        }
    }
);

watch(
    () => [
        visible.value,
        props.employee?.id ?? null,
        form.value.company_id,
        form.value.supervisor_employee_id,
        toYmd(form.value.supervisor_valid_from),
        toYmd(form.value.hired_at),
    ],
    () => {
        if (!visible.value) {
            return;
        }

        queueSupervisorPreview();
    }
);

const toPayload = () => {
    const birthDate =
        form.value.birth_date instanceof Date
            ? form.value.birth_date.toISOString().slice(0, 10)
            : null;
    const hiredAt =
        form.value.hired_at instanceof Date
            ? form.value.hired_at.toISOString().slice(0, 10)
            : null;

    return {
        company_id: form.value.company_id,
        first_name: form.value.first_name?.trim() || "",
        last_name: form.value.last_name?.trim() || "",
        email: form.value.email?.trim() || null,
        phone: form.value.phone?.trim() || null,
        position_id: form.value.position_id ? Number(form.value.position_id) : null,
        birth_date: birthDate,
        hired_at: hiredAt,
        active: !!form.value.active,
    };
};

const loadEmployeeDetails = async (employeeId) => {
    try {
        const response = await csrfFetch(`/employees/${employeeId}`, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        if (!response.ok) {
            return;
        }

        const payload = await response.json().catch(() => ({}));
        if (payload && typeof payload === "object") {
            fillFromEmployee(payload);
        }
    } catch {
        // no-op
    }
};

const runSupervisorPreview = async () => {
    const employeeId = Number(props.employee?.id || 0);
    const companyId = Number(form.value.company_id || 0);
    const supervisorEmployeeId = Number(form.value.supervisor_employee_id || 0);
    const effectiveFrom =
        toYmd(form.value.supervisor_valid_from) ||
        toYmd(form.value.hired_at) ||
        new Date().toISOString().slice(0, 10);

    if (!visible.value || !employeeId || !companyId || !supervisorEmployeeId) {
        supervisorPreview.value = null;
        return;
    }

    supervisorPreviewLoading.value = true;

    try {
        const params = new URLSearchParams({
            company_id: String(companyId),
            employee_id: String(employeeId),
            new_supervisor_employee_id: String(supervisorEmployeeId),
            mode: "employee_only",
            effective_from: effectiveFrom,
            at_date: effectiveFrom,
        });

        const response = await csrfFetch(`${route("org.hierarchy.move.preview")}?${params.toString()}`, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload?.message || trans("employees.messages.supervisor_preview_failed"));
        }

        supervisorPreview.value = payload?.data ?? null;
    } catch (error) {
        supervisorPreview.value = {
            warnings: [],
            errors: [error?.message || trans("employees.messages.supervisor_preview_failed")],
        };
    } finally {
        supervisorPreviewLoading.value = false;
    }
};

const queueSupervisorPreview = () => {
    if (supervisorPreviewTimer !== null) {
        clearTimeout(supervisorPreviewTimer);
    }

    supervisorPreviewTimer = setTimeout(() => {
        runSupervisorPreview();
    }, 400);
};

const supervisorPreviewHasErrors = computed(
    () => (supervisorPreview.value?.errors ?? []).length > 0,
);

const submit = async () => {
    const id = props.employee?.id;
    if (!id) {
        errors.value = { _general: trans("employees.dialogs.none_selected") };
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        if (form.value.supervisor_employee_id) {
            await runSupervisorPreview();

            if (supervisorPreviewHasErrors.value) {
                errors.value = {
                    supervisor_employee_id: supervisorPreview.value?.errors ?? [
                        trans("employees.messages.supervisor_preview_failed"),
                    ],
                };
                saving.value = false;
                return;
            }
        }

        const res = await csrfFetch(`/employees/${id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(toPayload()),
        });

        if (res.status === 422) {
            const body = await res.json().catch(() => ({}));
            errors.value = body?.errors ?? {};
            saving.value = false;
            return;
        }

        if (!res.ok) {
            let msg = trans("employees.messages.save_failed_http", {
                status: res.status,
            });
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        if (form.value.supervisor_employee_id) {
            const validFrom =
                form.value.supervisor_valid_from instanceof Date
                    ? form.value.supervisor_valid_from.toISOString().slice(0, 10)
                    : form.value.hired_at instanceof Date
                      ? form.value.hired_at.toISOString().slice(0, 10)
                      : new Date().toISOString().slice(0, 10);

            const supervisorRes = await csrfFetch(`/employees/${id}/supervisor`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({
                    employee_id: Number(id),
                    supervisor_employee_id: form.value.supervisor_employee_id
                        ? Number(form.value.supervisor_employee_id)
                        : null,
                    valid_from: validFrom,
                }),
            });

            if (supervisorRes.status === 422) {
                const body = await supervisorRes.json().catch(() => ({}));
                errors.value = body?.errors ?? {};
                saving.value = false;
                return;
            }

            if (!supervisorRes.ok) {
                throw new Error(
                    trans("employees.messages.supervisor_save_failed_http", {
                        status: supervisorRes.status,
                    }),
                );
            }
        }

        const profileResponse = await EmployeeLeaveProfileService.updateProfile(id, toProfilePayload());
        fillProfile(profileResponse?.data?.data ?? {});

        visible.value = false;
        emit("saved", trans("employees.messages.updated_success"));
    } catch (e) {
        const profileValidationErrors = EmployeeLeaveProfileService.extractErrors(e);
        if (profileValidationErrors) {
            profileErrors.value = profileValidationErrors;
        } else {
            errors.value = { _general: e?.message || trans("common.unknown_error") };
        }

        await ErrorService.logClientError(e, {
            category: "employee_edit_modal_save",
            data: { employee_id: id },
        });
    } finally {
        saving.value = false;
    }
};

const toProfilePayload = () => {
    return {
        children_count: Number(profileForm.value.children_count ?? 0),
        disabled_children_count: Number(profileForm.value.disabled_children_count ?? 0),
        is_disabled: !!profileForm.value.is_disabled,
    };
};

const refreshEntitlement = async () => {
    const id = props.employee?.id;
    if (!id) {
        return;
    }

    await loadEntitlement(id);
};

const profileFieldError = (key) => {
    const error = profileErrors.value?.[key];
    return Array.isArray(error) ? error[0] : error || null;
};

const close = () => {
    visible.value = false;
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('employees.dialogs.edit_title')"
        :style="{ width: '720px' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="errors?._general" class="mb-4 border p-3">
            <div class="font-semibold">{{ $t("common.error") }}</div>
            <div class="text-sm">{{ errors._general }}</div>
        </div>

        <EmployeeFields
            v-model="form"
            :errors="errors"
            :disabled="saving"
            :lockCompany="lockCompany"
        />

        <section class="mt-4 rounded-lg border border-surface-200 p-4">
            <div class="mb-3">
                <h3 class="text-lg font-semibold">{{ $t("employees.sections.supervisor") }}</h3>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $t("employees.form.supervisor") }}</label>
                    <SupervisorSelector
                        v-model="form.supervisor_employee_id"
                        :company-id="form.company_id"
                        :employee-id="props.employee?.id"
                        :placeholder="$t('employees.form.select_supervisor')"
                        :disabled="saving"
                    />
                    <div v-if="errors?.supervisor_employee_id" class="mt-1 text-sm text-red-600">
                        {{ Array.isArray(errors.supervisor_employee_id) ? errors.supervisor_employee_id[0] : errors.supervisor_employee_id }}
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ $t("employees.form.supervisor_valid_from") }}</label>
                    <DatePicker
                        v-model="form.supervisor_valid_from"
                        class="w-full"
                        dateFormat="yy-mm-dd"
                        showIcon
                        :disabled="saving"
                    />
                </div>
            </div>

            <div v-if="form.supervisor_employee_id" class="mt-3 space-y-2">
                <div class="text-xs text-surface-500">
                    {{ supervisorPreviewLoading ? $t("employees.messages.preview_running") : $t("employees.messages.preview_help") }}
                </div>

                <Message
                    v-for="warning in supervisorPreview?.warnings || []"
                    :key="`supervisor-warning-${warning}`"
                    severity="warn"
                    :closable="false"
                >
                    {{ warning }}
                </Message>

                <Message
                    v-for="error in supervisorPreview?.errors || []"
                    :key="`supervisor-error-${error}`"
                    severity="error"
                    :closable="false"
                >
                    {{ error }}
                </Message>

                <Message
                    v-if="!supervisorPreviewLoading && supervisorPreview && !supervisorPreviewHasErrors"
                    severity="success"
                    :closable="false"
                >
                    {{ $t("employees.messages.supervisor_integrity_ok") }}
                </Message>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="px-2 py-1 text-left">{{ $t("columns.date_from") }}</th>
                            <th class="px-2 py-1 text-left">{{ $t("columns.date_to") }}</th>
                            <th class="px-2 py-1 text-left">{{ $t("employees.form.supervisor") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in supervisorHistory" :key="row.id" class="border-b border-surface-100">
                            <td class="px-2 py-1">{{ row.valid_from }}</td>
                            <td class="px-2 py-1">{{ row.valid_to || '-' }}</td>
                            <td class="px-2 py-1">{{ row.supervisor_name }}</td>
                        </tr>
                        <tr v-if="!supervisorHistory.length">
                            <td class="px-2 py-2 text-surface-500" colspan="3">{{ $t("employees.messages.no_supervisor_history") }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Divider />

        <section class="rounded-lg border border-surface-200 p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">{{ $t("employees.sections.leave_profile") }}</h3>
                    <p class="text-sm text-surface-500">
                        {{ $t("employees.sections.leave_profile_help") }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button
                        :label="$t('employees.leave_profile.refresh_entitlement')"
                        icon="pi pi-refresh"
                        severity="secondary"
                        text
                        :loading="entitlementLoading"
                        :disabled="entitlementLoading || saving"
                        @click="refreshEntitlement"
                    />
                </div>
            </div>

            <div v-if="profileErrors?._general" class="mb-4 rounded border p-3">
                <div class="font-semibold">{{ $t("common.error") }}</div>
                <div class="text-sm">{{ profileErrors._general }}</div>
            </div>

            <div v-if="profileLoading" class="py-4 text-sm text-surface-500">
                {{ $t("employees.messages.leave_profile_loading") }}
            </div>

            <div v-else class="space-y-4">
                <LeaveProfileFields
                    v-model="profileForm"
                    :errors="profileErrors"
                    :disabled="saving"
                />

                <div class="rounded-md bg-surface-50 p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="font-medium">{{ $t("employees.sections.entitlement", { year: entitlementYear }) }}</div>
                        <div v-if="entitlementLoading" class="text-sm text-surface-500">{{ $t("employees.messages.entitlement_refreshing") }}</div>
                    </div>

                    <div v-if="entitlement" class="space-y-1 text-sm">
                        <div><strong>{{ $t("employees.leave_profile.total_minutes") }}:</strong> {{ entitlement.total_minutes }} {{ $t("common.minutes") }}</div>
                        <div><strong>{{ $t("employees.leave_profile.base_minutes") }}:</strong> {{ entitlement.base_minutes }} {{ $t("common.minutes") }}</div>
                        <div><strong>{{ $t("employees.leave_profile.age_bonus_minutes") }}:</strong> {{ entitlement.age_bonus_minutes }} {{ $t("common.minutes") }}</div>
                        <div><strong>{{ $t("employees.leave_profile.child_bonus_minutes") }}:</strong> {{ entitlement.child_bonus_minutes }} {{ $t("common.minutes") }}</div>
                        <div><strong>{{ $t("employees.leave_profile.youth_bonus_minutes") }}:</strong> {{ entitlement.youth_bonus_minutes }} {{ $t("common.minutes") }}</div>
                        <div><strong>{{ $t("employees.leave_profile.disability_bonus_minutes") }}:</strong> {{ entitlement.disability_bonus_minutes }} {{ $t("common.minutes") }}</div>
                    </div>
                    <div v-else class="text-sm text-surface-500">
                        {{ $t("employees.messages.entitlement_unavailable") }}
                    </div>
                </div>
            </div>
        </section>

        <template #footer>
            <!-- MÉGSEM -->
            <Button
                :label="$t('common.cancel')"
                severity="secondary"
                text
                :disabled="saving"
                @click="close"
            />
            <!-- MENTÉS -->
            <Button
                :label="$t('common.save')"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving || supervisorPreviewLoading || supervisorPreviewHasErrors || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
