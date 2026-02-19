<script setup>
import { computed, ref } from "vue";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import DatePicker from "primevue/datepicker";
import Dialog from "primevue/dialog";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import EmployeeWorkPatternService from "@/services/EmployeeWorkPatternService";
import { toYmd } from "@/helpers/functions.js";
import { useToast } from "primevue/usetoast";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    workPattern: { type: Object, default: null },
    canAssign: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const toast = useToast();
const saving = ref(false);
const errors = ref({});

const form = ref({
    employee_id: null,
    date_from: new Date(),
    date_to: null,
    is_primary: true,
});

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const title = computed(() => {
    const name = props.workPattern?.name ? `: ${props.workPattern.name}` : "";
    return `Dolgozó hozzárendelése${name}`;
});

const reset = () => {
    errors.value = {};
    form.value = {
        employee_id: null,
        date_from: new Date(),
        date_to: null,
        is_primary: true,
    };
};

const close = () => {
    if (saving.value) return;
    visible.value = false;
    reset();
};

const toPayload = () => ({
    // A backend az employee id-t URL paraméterből veszi, a pattern azonosító a body része.
    work_pattern_id: Number(props.workPattern?.id ?? 0),
    date_from: toYmd(form.value.date_from),
    date_to: toYmd(form.value.date_to),
    is_primary: !!form.value.is_primary,
});

const submit = async () => {
    saving.value = true;
    errors.value = {};

    try {
        const employeeId = Number(form.value.employee_id ?? 0);
        if (!employeeId) {
            errors.value.employee_id = "A dolgozó kiválasztása kötelező.";
            return;
        }

        const payload = toPayload();
        if (!payload.work_pattern_id) {
            errors.value._global = "Hiányzó munkarend azonosító.";
            return;
        }

        const { data } = await EmployeeWorkPatternService.assign(employeeId, payload);

        emit("saved", data?.message || "Munkarend sikeresen hozzárendelve.");
        close();
    } catch (e) {
        const status = e?.response?.status;
        if (status === 422) {
            const bag = e?.response?.data?.errors ?? {};
            const flat = {};
            Object.keys(bag).forEach((k) => (flat[k] = bag[k]?.[0] ?? String(bag[k])));
            errors.value = flat;
        } else {
            const message = e?.response?.data?.message || e?.message || "Mentési hiba.";
            errors.value._global = message;
            toast.add({ severity: "error", summary: "Hiba", detail: message, life: 3500 });
        }
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="title"
        :style="{ width: '38rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="mb-1 block text-sm">Dolgozó</label>
                <EmployeeSelector
                    v-model="form.employee_id"
                    :companyId="props.workPattern?.company_id ?? null"
                    :onlyActive="false"
                    placeholder="Válassz dolgozót..."
                />
                <div v-if="errors?.employee_id" class="mt-1 text-sm text-red-600">
                    {{ errors.employee_id }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm">Érvényes ettől</label>
                <DatePicker v-model="form.date_from" showIcon dateFormat="yy-mm-dd" class="w-full" />
                <div v-if="errors?.date_from" class="mt-1 text-sm text-red-600">
                    {{ errors.date_from }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm">Érvényes eddig</label>
                <DatePicker v-model="form.date_to" showIcon dateFormat="yy-mm-dd" class="w-full" />
                <div v-if="errors?.date_to" class="mt-1 text-sm text-red-600">
                    {{ errors.date_to }}
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Checkbox v-model="form.is_primary" binary inputId="assign-primary" />
                <label for="assign-primary" class="text-sm">Elsődleges</label>
            </div>

            <div v-if="errors?._global" class="text-sm text-red-600">
                {{ errors._global }}
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    :disabled="saving"
                    @click="close"
                />
                <Button
                    label="Hozzárendelés"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || !props.canAssign"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
