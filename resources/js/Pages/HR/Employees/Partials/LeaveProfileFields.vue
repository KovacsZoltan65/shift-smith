<script setup>

const props = defineProps({
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const fieldError = (key) => {
    const error = props.errors?.[key];
    return Array.isArray(error) ? error[0] : error || null;
};
</script>

<template>
    <div class="space-y-4">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="flex items-end gap-2">
                <Checkbox
                    :model-value="modelValue.is_disabled"
                    binary
                    :disabled="disabled"
                    @update:model-value="emit('update:modelValue', { ...modelValue, is_disabled: !!$event })"
                />
                <label class="text-sm">{{ $t("employees.leave_profile.disability") }}</label>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("employees.leave_profile.children_count") }}</label>
                <InputNumber
                    :model-value="modelValue.children_count"
                    inputClass="w-full"
                    class="w-full"
                    :useGrouping="false"
                    :min="0"
                    :max="20"
                    :disabled="disabled"
                    @update:model-value="emit('update:modelValue', { ...modelValue, children_count: Number($event ?? 0) })"
                />
                <div v-if="fieldError('children_count')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("children_count") }}
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">{{ $t("employees.leave_profile.disabled_children_count") }}</label>
                <InputNumber
                    :model-value="modelValue.disabled_children_count"
                    inputClass="w-full"
                    class="w-full"
                    :useGrouping="false"
                    :min="0"
                    :max="20"
                    :disabled="disabled"
                    @update:model-value="emit('update:modelValue', { ...modelValue, disabled_children_count: Number($event ?? 0) })"
                />
                <div v-if="fieldError('disabled_children_count')" class="mt-1 text-sm text-red-600">
                    {{ fieldError("disabled_children_count") }}
                </div>
            </div>
        </div>
    </div>
</template>
