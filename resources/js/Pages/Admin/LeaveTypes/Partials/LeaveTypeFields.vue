<script setup>
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Select from "primevue/select";

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
    isEdit: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const categories = [
    { label: "Szabadsag", value: "leave" },
    { label: "Betegszabadsag", value: "sick_leave" },
    { label: "Fizetett tavollet", value: "paid_absence" },
    { label: "Fizetes nelkuli tavollet", value: "unpaid_absence" },
];

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
                title="A kód rendszerazonosító, automatikusan generálódik."
                data-testid="leave-type-code"
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
                data-testid="leave-type-name"
                @update:modelValue="(value) => set('name', String(value ?? '').trimStart())"
            />
            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div class="md:col-span-2">
            <label class="mb-1 block text-sm">Kategoria</label>
            <Select
                :modelValue="modelValue?.category ?? 'leave'"
                :options="categories"
                optionLabel="label"
                optionValue="value"
                class="w-full"
                :disabled="disabled"
                data-testid="leave-type-category"
                @update:modelValue="(value) => set('category', value)"
            />
            <div v-if="errors?.category" class="mt-1 text-sm text-red-600">{{ errors.category }}</div>
        </div>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-3">
        <label class="flex items-center gap-2 text-sm">
            <Checkbox
                inputId="leave-type-affects"
                :modelValue="!!modelValue?.affects_leave_balance"
                binary
                :disabled="disabled"
                data-testid="leave-type-affects"
                @update:modelValue="(value) => set('affects_leave_balance', !!value)"
            />
            Keretet csokkenti
        </label>

        <label class="flex items-center gap-2 text-sm">
            <Checkbox
                inputId="leave-type-approval"
                :modelValue="!!modelValue?.requires_approval"
                binary
                :disabled="disabled"
                data-testid="leave-type-approval"
                @update:modelValue="(value) => set('requires_approval', !!value)"
            />
            Jovahagyas koteles
        </label>

        <label class="flex items-center gap-2 text-sm">
            <Checkbox
                inputId="leave-type-active"
                :modelValue="!!modelValue?.active"
                binary
                :disabled="disabled"
                data-testid="leave-type-active"
                @update:modelValue="(value) => set('active', !!value)"
            />
            Aktiv
        </label>
    </div>
</template>
