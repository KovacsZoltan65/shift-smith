<script setup>
import { computed } from "vue";
import { trans } from "laravel-vue-i18n";


const props = defineProps({
    modelValue: { type: Object, required: true }, // { name, guard_name }
    defaultGuard: { type: String, default: "web" },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const form = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const guardOptions = computed(() => [
    { label: trans("permissions.guards.web"), value: "web" },
    { label: trans("permissions.guards.api"), value: "api" },
]);

const set = (key, value) => {
    form.value = { ...form.value, [key]: value };
};
</script>

<template>
    <div class="space-y-4">
        <!-- Name -->
        <div>
            <label class="block text-sm mb-1">{{ trans("columns.name") }}</label>

            <InputText
                :modelValue="form.name"
                class="w-full"
                :disabled="disabled"
                :placeholder="trans('permissions.form.name_placeholder')"
                @update:modelValue="(v) => set('name', v)"
            />

            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
            </div>
        </div>

        <!-- Guard -->
        <div>
            <label class="block text-sm mb-1">{{ trans("permissions.fields.guard_name") }}</label>

            <Select
                :modelValue="form.guard_name || defaultGuard"
                class="w-full"
                :disabled="disabled"
                :options="guardOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="trans('permissions.form.guard_placeholder')"
                @update:modelValue="(v) => set('guard_name', v)"
            />

            <div v-if="errors?.guard_name" class="mt-1 text-sm text-red-600">
                {{ errors.guard_name }}
            </div>
        </div>
    </div>
</template>
