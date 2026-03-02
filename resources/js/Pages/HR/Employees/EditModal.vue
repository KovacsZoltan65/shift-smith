<script setup>
import { ref, watch, computed } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Divider from "primevue/divider";
import { useToast } from "primevue/usetoast";

import EmployeeFields from "@/Pages/HR/Employees/Partials/EmployeeFields.vue";
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

const form = ref({
    company_id: null,
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    position_id: null,
    hired_at: null,
    active: true,
});

const profileForm = ref({
    birth_date: null,
    children_count: 0,
    disabled_children_count: 0,
    is_disabled: false,
});

const reset = () => {
    errors.value = {};
    profileErrors.value = {};
    saving.value = false;
    profileLoading.value = false;
    entitlementLoading.value = false;
    entitlement.value = null;
    form.value = {
        company_id: null,
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        position_id: null,
        hired_at: null,
        active: true,
    };
    profileForm.value = {
        birth_date: null,
        children_count: 0,
        disabled_children_count: 0,
        is_disabled: false,
    };
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
        hired_at: parseDate(emp.hired_at),
        active: emp.active ?? true,
    };
};

const fillProfile = (profile) => {
    profileForm.value = {
        birth_date: parseDate(profile?.birth_date ?? null),
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
        const message = error?.response?.data?.message || "A szabadság profil betöltése sikertelen.";
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
            summary: "Hiba",
            detail: error?.response?.data?.message || "A jogosultság lekérése sikertelen.",
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
                loadLeaveProfile(props.employee.id);
                loadEntitlement(props.employee.id);
            }
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
                loadLeaveProfile(emp.id);
                loadEntitlement(emp.id);
            }
        }
    }
);

const toPayload = () => {
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
        hired_at: hiredAt,
        active: !!form.value.active,
    };
};

const submit = async () => {
    const id = props.employee?.id;
    if (!id) {
        errors.value = { _general: "Nincs kiválasztott dolgozó (id hiányzik)." };
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
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
            let msg = `Mentés sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        const profileResponse = await EmployeeLeaveProfileService.updateProfile(id, toProfilePayload());
        fillProfile(profileResponse?.data?.data ?? {});

        visible.value = false;
        emit("saved", "Dolgozó és szabadság profil frissítve.");
    } catch (e) {
        const profileValidationErrors = EmployeeLeaveProfileService.extractErrors(e);
        if (profileValidationErrors) {
            profileErrors.value = profileValidationErrors;
        } else {
            errors.value = { _general: e?.message || "Ismeretlen hiba" };
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
    const birthDate =
        profileForm.value.birth_date instanceof Date
            ? profileForm.value.birth_date.toISOString().slice(0, 10)
            : null;

    return {
        birth_date: birthDate,
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
        header="Dolgozó szerkesztése"
        :style="{ width: '720px' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="errors?._general" class="mb-4 border p-3">
            <div class="font-semibold">Hiba</div>
            <div class="text-sm">{{ errors._general }}</div>
        </div>

        <EmployeeFields
            v-model="form"
            :errors="errors"
            :disabled="saving"
            :lockCompany="lockCompany"
        />

        <Divider />

        <section class="rounded-lg border border-surface-200 p-4">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">Szabadság profil</h3>
                    <p class="text-sm text-surface-500">
                        Az éves szabadság jogosultság kalkulációjához használt adatok.
                    </p>
                </div>

                <div class="flex gap-2">
                    <Button
                        label="Jogosultság frissítése"
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
                <div class="font-semibold">Hiba</div>
                <div class="text-sm">{{ profileErrors._general }}</div>
            </div>

            <div v-if="profileLoading" class="py-4 text-sm text-surface-500">
                Szabadság profil betöltése...
            </div>

            <div v-else class="space-y-4">
                <LeaveProfileFields
                    v-model="profileForm"
                    :errors="profileErrors"
                    :disabled="saving"
                />

                <div class="rounded-md bg-surface-50 p-4">
                    <div class="mb-2 flex items-center justify-between gap-3">
                        <div class="font-medium">Jogosultság ({{ entitlementYear }})</div>
                        <div v-if="entitlementLoading" class="text-sm text-surface-500">Frissítés...</div>
                    </div>

                    <div v-if="entitlement" class="space-y-1 text-sm">
                        <div><strong>Összesen:</strong> {{ entitlement.total_minutes }} perc</div>
                        <div><strong>Alap:</strong> {{ entitlement.base_minutes }} perc</div>
                        <div><strong>Életkor:</strong> {{ entitlement.age_bonus_minutes }} perc</div>
                        <div><strong>Gyermek:</strong> {{ entitlement.child_bonus_minutes }} perc</div>
                        <div><strong>Fiatal munkavállaló:</strong> {{ entitlement.youth_bonus_minutes }} perc</div>
                        <div><strong>Fogyatékosság:</strong> {{ entitlement.disability_bonus_minutes }} perc</div>
                    </div>
                    <div v-else class="text-sm text-surface-500">
                        Jogosultsági adat még nem érhető el.
                    </div>
                </div>
            </div>
        </section>

        <template #footer>
            <!-- MÉGSEM -->
            <Button
                label="Mégse"
                severity="secondary"
                text
                :disabled="saving"
                @click="close"
            />
            <!-- MENTÉS -->
            <Button
                label="Mentés"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving || !props.canUpdate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
