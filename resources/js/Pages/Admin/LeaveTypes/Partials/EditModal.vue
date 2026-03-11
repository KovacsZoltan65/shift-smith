<script setup>
import { computed, ref, watch } from "vue";


import LeaveTypeFields from "@/Pages/Admin/LeaveTypes/Partials/LeaveTypeFields.vue";
import LeaveTypeService from "@/services/LeaveTypeService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    leaveType: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
    categoryOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "saved"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const loading = ref(false);
const saving = ref(false);
const errors = ref({});
const form = ref({
    name: "",
    category: "leave",
    affects_leave_balance: true,
    requires_approval: true,
    active: true,
});

const reset = () => {
    loading.value = false;
    saving.value = false;
    errors.value = {};
    form.value = {
        code: "",
        name: "",
        category: "leave",
        affects_leave_balance: true,
        requires_approval: true,
        active: true,
    };
};

const close = () => {
    open.value = false;
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};

    try {
        const { data } = await LeaveTypeService.show(id);
        const row = data?.data ?? {};

        form.value = {
            code: row.code ?? "",
            name: row.name ?? "",
            category: row.category ?? "leave",
            affects_leave_balance: !!row.affects_leave_balance,
            requires_approval: !!row.requires_approval,
            active: !!row.active,
        };
    } catch (error) {
        errors.value = {
            _global: error?.response?.data?.message ?? error?.message ?? "Betoltes sikertelen.",
        };
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.modelValue,
    async (isOpen) => {
        if (!isOpen) {
            reset();
            return;
        }

        const id = Number(props.leaveType?.id ?? 0);
        if (!id) {
            errors.value = { _global: "Nincs kivalasztott szabadsag tipus." };
            return;
        }

        await load(id);
    },
);

const submit = async () => {
    const id = Number(props.leaveType?.id ?? 0);
    if (!id) {
        errors.value = { _global: "Nincs kivalasztott szabadsag tipus." };
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        await LeaveTypeService.update(id, {
            name: String(form.value.name ?? "").trim(),
            category: form.value.category,
            affects_leave_balance: !!form.value.affects_leave_balance,
            requires_approval: !!form.value.requires_approval,
            active: !!form.value.active,
        });
        emit("saved", "Szabadsag tipus frissitve.");
        close();
    } catch (error) {
        errors.value = LeaveTypeService.extractErrors(error) ?? {
            _global: error?.response?.data?.message ?? error?.message ?? "Mentes sikertelen.",
        };
    } finally {
        saving.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Szabadsag tipus szerkesztese"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="loading" class="text-sm text-slate-500">Betoltes...</div>

        <template v-else>
            <LeaveTypeFields
                v-model="form"
                :errors="errors"
                :disabled="saving"
                :isEdit="true"
                :categoryOptions="categoryOptions"
            />
            <div v-if="errors?._global" class="mt-3 text-sm text-red-600">{{ errors._global }}</div>
        </template>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Megse" severity="secondary" :disabled="saving" @click="close" />
                <Button
                    label="Mentes"
                    icon="pi pi-check"
                    :loading="saving"
                    :disabled="saving || loading || !canUpdate"
                    data-testid="leave-type-edit-save"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
