<script setup>
import SharedCreateModal from "@/Pages/Companies/CreateModal.vue";

const props = defineProps({
    modelValue: Boolean,
    canCreate: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "saved"]);
</script>

<template>
    <SharedCreateModal
        :modelValue="props.modelValue"
        :canCreate="props.canCreate"
        endpointBase="/hq/companies"
        :tenantGroupFieldEnabled="true"
        @update:modelValue="emit('update:modelValue', $event)"
        @saved="emit('saved', $event)"
    />
</template>
