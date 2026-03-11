<script setup>
import { computed } from "vue";
import EmployeeWorkPatternPanel from "@/Pages/HR/Employees/Partials/EmployeeWorkPatternPanel.vue";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    employee: { type: Object, default: null },
    canAssign: { type: Boolean, default: false },
    canUnassign: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit("update:modelValue", v),
});
</script>

<template>
    <Dialog
        v-model:visible="visible"
        modal
        :header="$t('employees.dialogs.work_pattern_title')"
        :style="{ width: '72rem' }"
    >
        <div v-if="!props.employee?.id" class="text-sm text-gray-600">
            {{ $t("employees.dialogs.none_selected") }}
        </div>
        <EmployeeWorkPatternPanel
            v-else
            :employeeId="props.employee.id"
            :companyId="props.employee.company_id"
            :canAssign="props.canAssign"
            :canUnassign="props.canUnassign"
        />
    </Dialog>
</template>
