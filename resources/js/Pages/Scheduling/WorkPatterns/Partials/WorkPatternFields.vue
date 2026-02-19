<script setup>
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Textarea from "primevue/textarea";

const props = defineProps({
    modelValue: { type: Object, required: true },
    companyId: { type: [Number, String, null], default: null },
    disabled: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const form = props.modelValue;

const set = (key, value) => emit("update:modelValue", { ...form, [key]: value });

const typeOptions = [
    { label: "Fix heti", value: "fixed_weekly" },
    { label: "Rotációs", value: "rotating_shifts" },
    { label: "Egyedi", value: "custom" },
];

const toIntOrNull = (v) => {
    if (v === null || v === undefined || v === "") return null;
    const n = Number(v);
    return Number.isFinite(n) ? Math.trunc(n) : null;
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="mb-1 block text-sm">Név</label>
            <InputText
                class="w-full"
                :modelValue="form.name"
                :disabled="disabled"
                @update:modelValue="(v) => set('name', String(v ?? '').trimStart())"
            />
            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">{{ errors.name }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Típus</label>
            <Select
                class="w-full"
                :options="typeOptions"
                optionLabel="label"
                optionValue="value"
                :modelValue="form.type"
                :disabled="disabled"
                @update:modelValue="(v) => set('type', v)"
            />
            <div v-if="errors?.type" class="mt-1 text-sm text-red-600">{{ errors.type }}</div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Ciklus (nap)</label>
            <InputText
                class="w-full"
                type="number"
                :modelValue="form.cycle_length_days"
                :disabled="disabled"
                @update:modelValue="(v) => set('cycle_length_days', toIntOrNull(v))"
            />
            <div v-if="errors?.cycle_length_days" class="mt-1 text-sm text-red-600">
                {{ errors.cycle_length_days }}
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm">Heti perc</label>
            <InputText
                class="w-full"
                type="number"
                :modelValue="form.weekly_minutes"
                :disabled="disabled"
                @update:modelValue="(v) => set('weekly_minutes', toIntOrNull(v))"
            />
            <div v-if="errors?.weekly_minutes" class="mt-1 text-sm text-red-600">
                {{ errors.weekly_minutes }}
            </div>
        </div>
    </div>

    <div class="mt-4">
        <label class="mb-1 block text-sm">Meta (JSON szöveg)</label>
        <Textarea
            class="w-full"
            rows="4"
            :modelValue="form.metaText"
            :disabled="disabled"
            @update:modelValue="(v) => set('metaText', String(v ?? ''))"
        />
        <div v-if="errors?.meta" class="mt-1 text-sm text-red-600">{{ errors.meta }}</div>
    </div>

    <div class="mt-4 flex items-center gap-2">
        <Checkbox
            inputId="wp-active"
            :modelValue="!!form.active"
            binary
            :disabled="disabled"
            @update:modelValue="(v) => set('active', !!v)"
        />
        <label for="wp-active" class="text-sm">Aktív</label>
    </div>
</template>
