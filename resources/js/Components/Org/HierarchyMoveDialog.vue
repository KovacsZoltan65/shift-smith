<script setup>
import { computed, ref, watch } from "vue";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    visible: { type: Boolean, default: false },
    mode: { type: String, default: "employee_only" },
    companyId: { type: Number, required: true },
    employeeId: { type: Number, default: null },
    employeeLabel: { type: String, default: "" },
    currentSupervisorId: { type: Number, default: null },
    atDate: { type: String, default: null },
    defaultEffectiveFrom: { type: String, default: null },
});

const emit = defineEmits(["update:visible", "moved"]);

const previewLoading = ref(false);
const executeLoading = ref(false);
const formError = ref("");
const previewData = ref(null);
const newSupervisorEmployeeId = ref(null);
const targetSupervisorForSubordinates = ref(null);
const subordinatesStrategy = ref("reassign_to_old_supervisor");
const effectiveFrom = ref(
    props.defaultEffectiveFrom
        ? new Date(props.defaultEffectiveFrom)
        : new Date(),
);

let previewTimer = null;

const strategyOptions = [
    { label: "Beosztottak maradnak a vezetővel", value: "keep_with_leader" },
    {
        label: "Beosztottak menjenek a régi főnökhöz",
        value: "reassign_to_old_supervisor",
    },
    {
        label: "Beosztottak menjenek egy másik vezetőhöz",
        value: "reassign_to_specific_supervisor",
    },
];

const modeLabelMap = {
    employee_only: "Dolgozó áthelyezése",
    leader_with_subordinates: "Vezető áthelyezése csapattal",
    leader_without_subordinates: "Vezető áthelyezése csapat nélkül",
    move_subordinates_only: "Beosztottak áthelyezése másik vezető alá",
};

const modeLabel = computed(() => modeLabelMap[props.mode] ?? "Áthelyezés");

const needsNewSupervisor = computed(() =>
    [
        "employee_only",
        "leader_with_subordinates",
        "leader_without_subordinates",
    ].includes(props.mode),
);

const needsStrategy = computed(
    () => props.mode === "leader_without_subordinates",
);

const needsTargetSupervisor = computed(
    () =>
        props.mode === "move_subordinates_only" ||
        (props.mode === "leader_without_subordinates" &&
            subordinatesStrategy.value === "reassign_to_specific_supervisor"),
);

const ymd = (value) => {
    const date = value instanceof Date ? value : new Date(value ?? Date.now());
    if (Number.isNaN(date.getTime())) {
        const fallback = new Date();
        return `${fallback.getFullYear()}-${String(fallback.getMonth() + 1).padStart(2, "0")}-${String(fallback.getDate()).padStart(2, "0")}`;
    }

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`;
};

const previewPayload = computed(() => ({
    company_id: Number(props.companyId || 0),
    employee_id: Number(props.employeeId || 0),
    new_supervisor_employee_id: needsNewSupervisor.value
        ? newSupervisorEmployeeId.value
        : null,
    mode: props.mode,
    effective_from: ymd(effectiveFrom.value),
    at_date: props.atDate || ymd(new Date()),
    subordinates_strategy: needsStrategy.value
        ? subordinatesStrategy.value
        : null,
    target_supervisor_for_subordinates: needsTargetSupervisor.value
        ? targetSupervisorForSubordinates.value
        : null,
}));

const canPreview = computed(() => {
    if (
        !props.visible ||
        !previewPayload.value.company_id ||
        !previewPayload.value.employee_id
    ) {
        return false;
    }

    if (
        needsNewSupervisor.value &&
        !previewPayload.value.new_supervisor_employee_id
    ) {
        return false;
    }

    if (
        needsTargetSupervisor.value &&
        !previewPayload.value.target_supervisor_for_subordinates
    ) {
        return false;
    }

    return true;
});

const previewHasErrors = computed(
    () => (previewData.value?.errors ?? []).length > 0,
);
const canExecute = computed(
    () =>
        canPreview.value &&
        !previewLoading.value &&
        !executeLoading.value &&
        !previewHasErrors.value,
);

const resetForm = () => {
    newSupervisorEmployeeId.value = null;
    targetSupervisorForSubordinates.value = null;
    subordinatesStrategy.value = "reassign_to_old_supervisor";
    effectiveFrom.value = props.defaultEffectiveFrom
        ? new Date(props.defaultEffectiveFrom)
        : new Date();
    previewData.value = null;
    formError.value = "";
};

const closeDialog = () => {
    emit("update:visible", false);
};

const runPreview = async () => {
    if (!canPreview.value) {
        previewData.value = null;
        return;
    }

    previewLoading.value = true;
    formError.value = "";

    try {
        const params = new URLSearchParams();
        Object.entries(previewPayload.value).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== "") {
                params.set(key, String(value));
            }
        });

        const response = await csrfFetch(
            `${route("org.hierarchy.move.preview")}?${params.toString()}`,
            {
                method: "GET",
                headers: { Accept: "application/json" },
            },
        );
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(
                payload?.message || "Az áthelyezés előnézete sikertelen.",
            );
        }

        previewData.value = payload?.data ?? null;
    } catch (error) {
        previewData.value = null;
        formError.value =
            error instanceof Error
                ? error.message
                : "Az áthelyezés előnézete sikertelen.";
    } finally {
        previewLoading.value = false;
    }
};

const queuePreview = () => {
    if (previewTimer !== null) {
        clearTimeout(previewTimer);
    }

    previewTimer = setTimeout(() => {
        runPreview();
    }, 400);
};

const submitMove = async () => {
    if (!canExecute.value) {
        return;
    }

    executeLoading.value = true;
    formError.value = "";

    try {
        const response = await csrfFetch(route("org.hierarchy.move"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(previewPayload.value),
        });
        const payload = await response.json();

        if (!response.ok) {
            throw new Error(
                payload?.message || "Az áthelyezés végrehajtása sikertelen.",
            );
        }

        emit("moved", payload?.data ?? null);
        closeDialog();
    } catch (error) {
        formError.value =
            error instanceof Error
                ? error.message
                : "Az áthelyezés végrehajtása sikertelen.";
    } finally {
        executeLoading.value = false;
    }
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetForm();
            queuePreview();
            return;
        }

        if (previewTimer !== null) {
            clearTimeout(previewTimer);
            previewTimer = null;
        }
    },
);

watch(
    () => [
        props.mode,
        props.employeeId,
        props.companyId,
        props.atDate,
        newSupervisorEmployeeId.value,
        targetSupervisorForSubordinates.value,
        subordinatesStrategy.value,
        ymd(effectiveFrom.value),
    ],
    () => {
        if (props.visible) {
            queuePreview();
        }
    },
);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :draggable="false"
        :closable="!executeLoading"
        :style="{ width: '42rem', maxWidth: '96vw' }"
        :header="modeLabel"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <div
                    class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                >
                    Érintett dolgozó
                </div>
                <div class="mt-1 text-sm font-medium text-slate-800">
                    {{ employeeLabel || `#${employeeId}` }}
                </div>
            </div>

            <div v-if="needsNewSupervisor" class="space-y-2">
                <label class="block text-sm font-medium text-slate-700"
                    >Cél vezető</label
                >
                <EmployeeSelector
                    v-model="newSupervisorEmployeeId"
                    class="block w-full"
                    :company-id="companyId"
                    :server-search="true"
                    :exclude-employee-ids="[employeeId]"
                    placeholder="Cél vezető keresése..."
                />
            </div>

            <div v-if="needsStrategy" class="space-y-2">
                <label class="block text-sm font-medium text-slate-700"
                    >Beosztottak stratégiája</label
                >
                <Select
                    v-model="subordinatesStrategy"
                    :options="strategyOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                />
            </div>

            <div v-if="needsTargetSupervisor" class="space-y-2">
                <label class="block text-sm font-medium text-slate-700"
                    >Beosztottak cél vezetője</label
                >
                <EmployeeSelector
                    v-model="targetSupervisorForSubordinates"
                    :company-id="companyId"
                    :server-search="true"
                    :exclude-employee-ids="[employeeId]"
                    placeholder="Beosztottak cél vezetője..."
                    class="w-full"
                />
            </div>

            <div class="space-y-2">
                <label class="block text-sm font-medium text-slate-700"
                    >Hatálybalépés</label
                >
                <DatePicker
                    v-model="effectiveFrom"
                    dateFormat="yy-mm-dd"
                    showIcon
                    inputClass="w-full"
                    class="w-full [&_.p-datepicker-input]:w-full [&_.p-inputtext]:w-full"
                />
            </div>

            <div class="rounded-xl border border-slate-200 p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">
                            Előnézet
                        </div>
                        <div class="text-xs text-slate-500">
                            A végrehajtás előtt integritás és érintettség
                            ellenőrzés fut.
                        </div>
                    </div>
                    <Button
                        label="Előnézet"
                        icon="pi pi-search"
                        severity="secondary"
                        :loading="previewLoading"
                        :disabled="!canPreview || executeLoading"
                        @click="runPreview"
                    />
                </div>

                <Message
                    v-if="formError"
                    severity="error"
                    :closable="false"
                    class="mb-3"
                >
                    {{ formError }}
                </Message>

                <div v-if="previewData" class="space-y-3 text-sm">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div
                                class="text-xs uppercase tracking-wide text-slate-500"
                            >
                                Érintett dolgozók
                            </div>
                            <div
                                class="mt-1 text-lg font-semibold text-slate-800"
                            >
                                {{ previewData.affected_count || 0 }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <div
                                class="text-xs uppercase tracking-wide text-slate-500"
                            >
                                Hatálybalépés
                            </div>
                            <div
                                class="mt-1 text-lg font-semibold text-slate-800"
                            >
                                {{ previewData.meta?.effective_from || "-" }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="(previewData.affected_employees ?? []).length > 0"
                        class="space-y-2"
                    >
                        <div
                            class="text-xs font-semibold uppercase tracking-wide text-slate-500"
                        >
                            Rövid lista
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="employee in previewData.affected_employees"
                                :key="employee.id"
                                class="rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-700"
                            >
                                {{ employee.label }}
                            </span>
                        </div>
                    </div>

                    <Message
                        v-for="warning in previewData.warnings || []"
                        :key="`warning-${warning}`"
                        severity="warn"
                        :closable="false"
                    >
                        {{ warning }}
                    </Message>

                    <Message
                        v-for="error in previewData.errors || []"
                        :key="`error-${error}`"
                        severity="error"
                        :closable="false"
                    >
                        {{ error }}
                    </Message>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex items-center justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    text
                    :disabled="executeLoading"
                    @click="closeDialog"
                />
                <Button
                    label="Végrehajtás"
                    icon="pi pi-check"
                    :loading="executeLoading"
                    :disabled="!canExecute"
                    @click="submitMove"
                />
            </div>
        </template>
    </Dialog>
</template>
