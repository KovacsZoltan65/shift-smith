<script setup>
import { ref, watch, computed } from "vue";

import Dialog from "primevue/dialog";
import Button from "primevue/button";

import EmployeeFields from "@/Pages/HR/Employees/Partials/EmployeeFields.vue";
import SupervisorSelector from "@/Components/Selectors/SupervisorSelector.vue";
import RestoreEmployeeDialog from "@/Components/Employees/RestoreEmployeeDialog.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    defaultCompanyId: { type: [Number, String, null], default: null },
    canCreate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const saving = ref(false);
const errors = ref({});
const restoreDialogVisible = ref(false);
const restoreCandidate = ref(null);
const restoreLoading = ref(false);

const form = ref({
    company_id: props.defaultCompanyId ? Number(props.defaultCompanyId) : null,
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

const reset = () => {
    errors.value = {};
    saving.value = false;
    restoreDialogVisible.value = false;
    restoreCandidate.value = null;
    restoreLoading.value = false;
    form.value = {
        company_id: props.defaultCompanyId ? Number(props.defaultCompanyId) : null,
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
};

watch(
    () => props.modelValue,
    (open) => {
        if (open) reset();
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

const toSupervisorPayload = (employeeId) => {
    const validFrom =
        form.value.supervisor_valid_from instanceof Date
            ? form.value.supervisor_valid_from.toISOString().slice(0, 10)
            : form.value.hired_at instanceof Date
              ? form.value.hired_at.toISOString().slice(0, 10)
              : new Date().toISOString().slice(0, 10);

    return {
        employee_id: Number(employeeId),
        supervisor_employee_id: form.value.supervisor_employee_id ? Number(form.value.supervisor_employee_id) : null,
        valid_from: validFrom,
    };
};

const submit = async () => {
    saving.value = true;
    errors.value = {};
    restoreCandidate.value = null;

    try {
        const res = await csrfFetch("/employees", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(toPayload()),
        });

        if (res.status === 409) {
            const body = await res.json().catch(() => ({}));
            if (body?.restore_available && body?.employee) {
                restoreCandidate.value = body.employee;
                restoreDialogVisible.value = true;
                saving.value = false;
                return;
            }
        }

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

        const body = await res.json().catch(() => ({}));
        const employeeId = Number(body?.data?.id || 0);

        if (employeeId > 0 && form.value.supervisor_employee_id) {
            const supervisorPayload = toSupervisorPayload(employeeId);

            const supervisorResponse = await csrfFetch(`/employees/${employeeId}/supervisor`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify(supervisorPayload),
            });

            if (supervisorResponse.status === 422) {
                const supervisorErrors = await supervisorResponse.json().catch(() => ({}));
                errors.value = supervisorErrors?.errors ?? {};
                saving.value = false;
                return;
            }

            if (!supervisorResponse.ok) {
                throw new Error(`Felettes mentés sikertelen (HTTP ${supervisorResponse.status})`);
            }
        }

        visible.value = false;
        emit("saved", "Dolgozó létrehozva.");
    } catch (e) {
        errors.value = { _general: e?.message || "Ismeretlen hiba" };
    } finally {
        saving.value = false;
    }
};

const submitRestore = async () => {
    const employeeId = Number(restoreCandidate.value?.id || 0);
    if (!employeeId) {
        return;
    }

    restoreLoading.value = true;
    errors.value = {};

    try {
        const res = await csrfFetch(`/employees/${employeeId}/restore`, {
            method: "POST",
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
            restoreLoading.value = false;
            return;
        }

        if (!res.ok) {
            let msg = `Visszaállítás sikertelen (HTTP ${res.status})`;
            try {
                const body = await res.json();
                msg = body?.message || msg;
            } catch (_) {}
            throw new Error(msg);
        }

        restoreDialogVisible.value = false;
        visible.value = false;
        emit(
            "saved",
            "Dolgozó visszaállítva. A hierarchia hozzárendelés külön szükséges.",
        );
    } catch (e) {
        errors.value = { _general: e?.message || "Ismeretlen hiba" };
    } finally {
        restoreLoading.value = false;
    }
};

const close = () => {
    visible.value = false;
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        header="Új dolgozó"
        :style="{ width: '720px' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <RestoreEmployeeDialog
            :visible="restoreDialogVisible"
            :employee="restoreCandidate"
            :loading="restoreLoading"
            @update:visible="restoreDialogVisible = $event"
            @confirm="submitRestore"
        />

        <div v-if="errors?._general" class="mb-4 border p-3">
            <div class="font-semibold">Hiba</div>
            <div class="text-sm">{{ errors._general }}</div>
        </div>

        <EmployeeFields v-model="form" :errors="errors" :disabled="saving" />

        <div class="mt-4 space-y-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Felettes</label>
                <SupervisorSelector
                    v-model="form.supervisor_employee_id"
                    :company-id="form.company_id"
                    :disabled="saving"
                />
                <div v-if="errors?.supervisor_employee_id" class="mt-1 text-sm text-red-600">
                    {{ Array.isArray(errors.supervisor_employee_id) ? errors.supervisor_employee_id[0] : errors.supervisor_employee_id }}
                </div>
            </div>
        </div>

        <template #footer>
            <!-- CANCEL -->
            <Button
                label="Mégse"
                severity="secondary"
                text
                :disabled="saving"
                @click="close"
            />
            <!-- SAVE -->
            <Button
                label="Mentés"
                icon="pi pi-check"
                :loading="saving"
                :disabled="saving || !props.canCreate"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
