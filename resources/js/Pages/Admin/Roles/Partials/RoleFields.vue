<script setup>
import { computed, onMounted, ref } from "vue";
import { trans } from "laravel-vue-i18n";


const props = defineProps({
    modelValue: { type: Object, required: true }, // { name, guard_name, permission_ids: [] }
    defaultGuard: { type: String, default: "web" },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const form = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});

const guardOptions = [
    { label: trans("roles.guards.web"), value: "web" },
    { label: trans("roles.guards.api"), value: "api" },
];

const permissions = ref([]); // [{id,name}]
const loadingPermissions = ref(false);

const shouldUseFilter = computed(() => (permissions.value?.length ?? 0) > 10);

const set = (key, value) => {
    form.value = { ...form.value, [key]: value };
};

onMounted(async () => {
    loadingPermissions.value = true;
    try {
        const res = await fetch(`/admin/selectors/permissions`, {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                Accept: "application/json",
            },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data = await res.json();
        // endpoint: [{id,name}] (vagy {data:[...]})
        const list = Array.isArray(data) ? data : data?.data;

        permissions.value = (Array.isArray(list) ? list : []).map((p) => ({
            id: Number(p.id),
            name: p.name ?? p.label ?? String(p.id),
        }));
    } catch (e) {
        permissions.value = [];
        // direkt nincs toast innen; a modal/Index kezelje, ha kell
        console.error("Permissions selector failed:", e);
    } finally {
        loadingPermissions.value = false;
    }
});
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
                :placeholder="trans('roles.form.name_placeholder')"
                @update:modelValue="(v) => set('name', v)"
            />

            <div v-if="errors?.name" class="mt-1 text-sm text-red-600">
                {{ errors.name }}
            </div>
        </div>

        <!-- Guard -->
        <div>
            <label class="block text-sm mb-1">{{ trans("roles.fields.guard_name") }}</label>

            <Select
                :modelValue="form.guard_name || defaultGuard"
                class="w-full"
                :disabled="disabled"
                :options="guardOptions"
                optionLabel="label"
                optionValue="value"
                :placeholder="trans('roles.form.guard_placeholder')"
                @update:modelValue="(v) => set('guard_name', v)"
            />

            <div v-if="errors?.guard_name" class="mt-1 text-sm text-red-600">
                {{ errors.guard_name }}
            </div>
        </div>

        <!-- Permissions -->
        <div>
            <label class="block text-sm mb-1">{{ trans("permissions.title") }}</label>

            <MultiSelect
                :modelValue="form.permission_ids"
                class="w-full"
                :disabled="disabled"
                :loading="loadingPermissions"
                :options="permissions"
                optionLabel="name"
                optionValue="id"
                :filter="shouldUseFilter"
                :filterFields="['name']"
                display="chip"
                :placeholder="trans('roles.form.permissions_placeholder')"
                @update:modelValue="(v) => set('permission_ids', v)"
            />

            <div
                v-if="errors?.permission_ids || errors?.['permission_ids.0']"
                class="mt-1 text-sm text-red-600"
            >
                {{ errors.permission_ids || errors["permission_ids.0"] }}
            </div>

            <div class="mt-2 text-xs text-gray-500">
                {{ trans("roles.form.permissions_help") }}
            </div>
        </div>
    </div>
</template>
