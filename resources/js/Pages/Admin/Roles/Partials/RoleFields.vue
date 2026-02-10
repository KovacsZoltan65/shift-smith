<script setup>
import { computed } from "vue";

import InputText from "primevue/inputtext";
//import Select from "primevue/select";
//import RoleSelector from "../../../Components/Selectors/RoleSelector.vue";
//import MultiSelect from "primevue/multiselect";
import PermissionSelector from "@/Components/Selectors/PermissionSelector.vue";

const props = defineProps({
    modelValue: { type: Object, required: true }, // { name, guard_name, permission_ids: [] }
    permissions: { type: Array, default: () => [] }, // [{id,name}]
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
                placeholder="pl. manager"
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

        <!-- Permissions -->
        <div>
            <label class="block text-sm mb-1">Permissions</label>

            <!--<MultiSelect
                :modelValue="form.permission_ids"
                class="w-full"
                :disabled="disabled"
                :options="permissions"
                optionLabel="name"
                optionValue="id"
                filter
                display="chip"
                placeholder="Válassz jogosultságokat"
                @update:modelValue="(v) => set('permission_ids', v)"
            />-->
            <PermissionSelector
                :modelValue="form.permission_ids"
                class="w-full"
                :disabled="disabled"
                :options="permissions"
                optionLabel="name"
                optionValue="id"
                filter
                display="chip"
                placeholder="Válassz jogosultságokat"
                @update:modelValue="(v) => set('permission_ids', v)"
            />

            <div
                v-if="errors?.permission_ids || errors?.['permission_ids.0']"
                class="mt-1 text-sm text-red-600"
            >
                {{ errors.permission_ids || errors["permission_ids.0"] }}
            </div>

            <div class="mt-2 text-xs text-gray-500">
                Tipp: ha sok permission van, a filter mezőn gyorsan szűrsz.
            </div>
        </div>
    </div>
</template>
