<script setup>
import { computed, ref, watch } from "vue";
import LeaveCategoryFields from "@/Pages/Admin/LeaveCategories/Partials/LeaveCategoryFields.vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    canUpdate: { type: Boolean, default: false },
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
    code: "",
    name: "",
    description: "",
    active: true,
    order_index: 0,
});

const reset = () => {
    loading.value = false;
    saving.value = false;
    errors.value = {};
    form.value = {
        code: "",
        name: "",
        description: "",
        active: true,
        order_index: 0,
    };
};

const close = () => {
    open.value = false;
};

const load = async (id) => {
    loading.value = true;
    errors.value = {};

    try {
        const { data } = await LeaveCategoryService.show(id);
        const row = data?.data ?? {};

        form.value = {
            code: row.code ?? "",
            name: row.name ?? "",
            description: row.description ?? "",
            active: !!row.active,
            order_index: Number(row.order_index ?? 0),
        };
    } catch (error) {
        errors.value = {
            _global: error?.response?.data?.message ?? error?.message ?? "Betoltes sikertelen.",
        };
    } finally {
        loading.value = false;
    }
};

watch(() => props.modelValue, async (isOpen) => {
    if (!isOpen) {
        reset();
        return;
    }

    const id = Number(props.category?.id ?? 0);
    if (!id) {
        errors.value = { _global: "Nincs kivalasztott szabadsag kategoria." };
        return;
    }

    await load(id);
});

const submit = async () => {
    const id = Number(props.category?.id ?? 0);
    if (!id) {
        errors.value = { _global: "Nincs kivalasztott szabadsag kategoria." };
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        await LeaveCategoryService.update(id, {
            name: String(form.value.name ?? "").trim(),
            description: String(form.value.description ?? "").trim() || null,
            active: !!form.value.active,
            order_index: Number(form.value.order_index ?? 0),
        });
        emit("saved", "Szabadsag kategoria frissitve.");
        close();
    } catch (error) {
        errors.value = LeaveCategoryService.extractErrors(error) ?? {
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
        header="Szabadsag kategoria szerkesztese"
        :style="{ width: '42rem' }"
        :closable="!saving"
        :dismissableMask="!saving"
        @hide="reset"
    >
        <div v-if="loading" class="text-sm text-slate-500">Betoltes...</div>

        <template v-else>
            <LeaveCategoryFields v-model="form" :errors="errors" :disabled="saving" :isEdit="true" />
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
                    data-testid="leave-category-edit-save"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
