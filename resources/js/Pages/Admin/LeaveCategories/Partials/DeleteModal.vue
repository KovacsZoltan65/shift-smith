<script setup>
import { computed, ref, watch } from "vue";
import LeaveCategoryService from "@/services/LeaveCategoryService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    category: { type: Object, default: null },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "deleted"]);

const open = computed({
    get: () => props.modelValue,
    set: (value) => emit("update:modelValue", value),
});

const deleting = ref(false);
const error = ref("");

watch(() => open.value, (isOpen) => {
    if (isOpen) {
        error.value = "";
    }
});

const close = () => {
    open.value = false;
};

const submit = async () => {
    const id = Number(props.category?.id ?? 0);
    if (!id) {
        error.value = "Nincs kivalasztott szabadsag kategoria.";
        return;
    }

    deleting.value = true;
    error.value = "";

    try {
        await LeaveCategoryService.destroy(id);
        emit("deleted", "Szabadsag kategoria torolve.");
        close();
    } catch (requestError) {
        error.value = requestError?.response?.data?.message ?? requestError?.message ?? "Torles sikertelen.";
    } finally {
        deleting.value = false;
    }
};
</script>

<template>
    <Dialog
        v-model:visible="open"
        modal
        header="Szabadsag kategoria torlese"
        :style="{ width: '30rem' }"
        :closable="!deleting"
        :dismissableMask="!deleting"
    >
        <p class="text-sm text-slate-700">
            Biztosan torolni szeretned ezt a szabadsag kategoriat:
            <strong>{{ category?.name ?? category?.code ?? "#" }}</strong>
        </p>

        <div v-if="error" class="mt-3 text-sm text-red-600">{{ error }}</div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Megse" severity="secondary" :disabled="deleting" @click="close" />
                <Button
                    label="Torles"
                    icon="pi pi-trash"
                    severity="danger"
                    :loading="deleting"
                    :disabled="deleting || !canDelete"
                    data-testid="leave-category-delete-confirm"
                    @click="submit"
                />
            </div>
        </template>
    </Dialog>
</template>
