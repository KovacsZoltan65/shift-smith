<script setup>
import Checkbox from "primevue/checkbox";
import InputNumber from "primevue/inputnumber";
import InputText from "primevue/inputtext";
import Textarea from "primevue/textarea";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const set = (key, value) => emit("update:modelValue", { ...(props.modelValue ?? {}), [key]: value });
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm">Kod</label>
            <InputText
                class="w-full"
                :modelValue="modelValue?.code ?? 'Automatikusan generalodik'"
                readonly
                :disabled="disabled || !isEdit"
                data-testid="leave-category-code"
            />
            <div v-if="errors?.code" class="mt-1 text-sm text-red-600">{{ errors.code }}</div>
            <div class="mt-1 text-xs text-slate-500">A kodot a rendszer a nev alapjan generalja.</div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Nev</label>
            <InputText
                class="w-full"
                :modelValue="modelValue?.name ?? ''"
                :disabled="disabled"
                data-testid="leave-category-name"
                @update:modelValue="(value) => set('name', String(value ?? '').trimStart())"
            />
            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm">Leiras</label>
            <Textarea
                class="w-full"
                rows="4"
                :modelValue="modelValue?.description ?? ''"
                :disabled="disabled"
                data-testid="leave-category-description"
                @update:modelValue="(value) => set('description', String(value ?? '').trimStart())"
            />
            <div v-if="errors?.description" class="mt-1 text-sm text-red-600">{{ errors.description }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Sorrend</label>
            <InputNumber
                class="w-full"
                :modelValue="Number(modelValue?.order_index ?? 0)"
                :min="0"
                :max="100000"
                showButtons
                data-testid="leave-category-order-index"
                :disabled="disabled"
                @update:modelValue="(value) => set('order_index', Number(value ?? 0))"
            />
            <div v-if="errors?.order_index" class="mt-1 text-sm text-red-600">{{ errors.order_index }}</div>
        </div>

        <div class="flex items-center pt-7">
            <label class="flex items-center gap-2 text-sm">
                <Checkbox
                    inputId="leave-category-active"
                    :modelValue="!!modelValue?.active"
                    binary
                    :disabled="disabled"
                    data-testid="leave-category-active"
                    @update:modelValue="(value) => set('active', !!value)"
                />
                Aktiv
            </label>
        </div>
    </div>
</template>
