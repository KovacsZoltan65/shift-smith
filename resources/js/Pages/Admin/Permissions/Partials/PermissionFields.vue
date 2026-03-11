<script setup>
import { computed } from "vue";


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
    { label: "web", value: "web" },
    { label: "api", value: "api" },
]);

const set = (key, value) => {
    form.value = { ...form.value, [key]: value };
};
</script>

<template>
    <div class="space-y-4">
        <!-- Name -->
        <div>
            <label class="block text-sm mb-1">Név</label>

            <InputText
                :modelValue="form.name"
                class="w-full"
                :disabled="disabled"
                placeholder="pl. permissions.viewAny"
                @update:modelValue="(v) => set('name', v)"
            />

            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
            </div>
        </div>

        <!-- Guard -->
        <div>
            <label class="block text-sm mb-1">Guard</label>

            <Select
                :modelValue="form.guard_name || defaultGuard"
                class="w-full"
                :disabled="disabled"
                :options="guardOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="Válassz guard-ot"
                @update:modelValue="(v) => set('guard_name', v)"
            />

            <div v-if="errors?.guard_name" class="mt-1 text-sm text-red-600">
                {{ errors.guard_name }}
            </div>
        </div>
    </div>
</template>
