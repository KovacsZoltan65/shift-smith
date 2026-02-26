<script setup>
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";

const props = defineProps({
    modelValue: { type: Object, required: true },
    companyId: { type: [Number, String, null], default: null },
    disabled: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const form = props.modelValue;

const set = (key, value) => emit("update:modelValue", { ...form, [key]: value });

const toIntOrNull = (v) => {
    if (v === null || v === undefined || v === "") return null;
    const n = Number(v);
    return Number.isFinite(n) ? Math.trunc(n) : null;
};
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Név -->
        <div class="md:col-span-2">
            <label class="mb-1 block text-sm">Név</label>
            <InputText
                class="w-full"
                :modelValue="form.name"
                :disabled="disabled"
                @update:modelValue="(v) => set('name', String(v ?? '').trimStart())"
            />
            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
            </div>
        </div>

        <!-- Napi munkaidő -->
        <div>
            <label class="mb-1 block text-sm">Napi munkaidő (perc)</label>
            <InputText
                class="w-full"
                type="number"
                :modelValue="form.daily_work_minutes"
                :disabled="disabled"
                @update:modelValue="(v) => set('daily_work_minutes', toIntOrNull(v))"
            />
            <div v-if="errors?.daily_work_minutes" class="mt-1 text-sm text-red-600">
                {{ errors.daily_work_minutes }}
            </div>
        </div>

        <!-- Szünet -->
        <div>
            <label class="mb-1 block text-sm">Szünet (perc)</label>
            <InputText
                class="w-full"
                type="number"
                :modelValue="form.break_minutes"
                :disabled="disabled"
                @update:modelValue="(v) => set('break_minutes', toIntOrNull(v))"
            />
            <div v-if="errors?.break_minutes" class="mt-1 text-sm text-red-600">
                {{ errors.break_minutes }}
            </div>
        </div>

        <!-- Core törzsidő kezdete -->
        <div>
            <label class="mb-1 block text-sm">Core kezdés (HH:mm)</label>
            <InputText
                class="w-full"
                placeholder="10:00"
                :modelValue="form.core_start_time"
                :disabled="disabled"
                @update:modelValue="(v) => set('core_start_time', String(v ?? '').trim())"
            />
            <div v-if="errors?.core_start_time" class="mt-1 text-sm text-red-600">
                {{ errors.core_start_time }}
            </div>
            <div class="mt-1 text-xs text-gray-500">
                Ha üres, a munkarend nem rugalmas.
            </div>
        </div>

        <!-- Core törzsidő vége -->
        <div>
            <label class="mb-1 block text-sm">Core zárás (HH:mm)</label>
            <InputText
                class="w-full"
                placeholder="15:00"
                :modelValue="form.core_end_time"
                :disabled="disabled"
                @update:modelValue="(v) => set('core_end_time', String(v ?? '').trim())"
            />
            <div v-if="errors?.core_end_time" class="mt-1 text-sm text-red-600">
                {{ errors.core_end_time }}
            </div>
        </div>
    </div>

    <!-- Aktív -->
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
