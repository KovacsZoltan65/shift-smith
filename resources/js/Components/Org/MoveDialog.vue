<script setup>
import { computed, ref, watch } from "vue";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    visible: { type: Boolean, default: false },
    companyId: { type: Number, required: true },
    employeeId: { type: Number, default: null },
    mode: { type: String, default: "employee_only" },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["update:visible", "moved"]);

const newSupervisorEmployeeId = ref(null);
const targetSupervisorForSubordinates = ref(null);
const subordinatesStrategy = ref("reassign_to_old_supervisor");
const effectiveFrom = ref(new Date());
const previewLoading = ref(false);
const executeLoading = ref(false);
const previewResult = ref(null);
const formError = ref("");
let previewTimer = null;

const strategyOptions = [
    { label: "Beosztottak maradnak a vezetővel", value: "keep_with_leader" },
    { label: "Beosztottak menjenek a régi főnökhöz", value: "reassign_to_old_supervisor" },
    { label: "Beosztottak menjenek egy másik vezetőhöz", value: "reassign_to_specific_supervisor" },
];

const modeLabel = computed(() => {
    if (props.mode === "employee_only") return "Dolgozó áthelyezése";
    if (props.mode === "leader_with_subordinates") return "Vezető áthelyezése (csapattal)";
    if (props.mode === "leader_without_subordinates") return "Vezető áthelyezése (csapat nélkül)";
    return "Beosztottak áthelyezése másik vezető alá";
});

const needsNewSupervisor = computed(
    () => props.mode === "employee_only" || props.mode === "leader_with_subordinates" || props.mode === "leader_without_subordinates",
);

const needsTargetSupervisor = computed(
    () =>
        props.mode === "move_subordinates_only" ||
        (props.mode === "leader_without_subordinates" && subordinatesStrategy.value === "reassign_to_specific_supervisor"),
);

const effectiveFromYmd = computed(() => {
    const value = effectiveFrom.value instanceof Date ? effectiveFrom.value : new Date();
    const y = value.getFullYear();
    const m = String(value.getMonth() + 1).padStart(2, "0");
    const d = String(value.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
});

const canPreview = computed(() => {
    if (!props.companyId || !props.employeeId) return false;
    if (needsNewSupervisor.value && !newSupervisorEmployeeId.value) return false;
    if (needsTargetSupervisor.value && !targetSupervisorForSubordinates.value) return false;
    return true;
});

const canExecute = computed(() => {
    if (!previewResult.value) return false;
    if (Array.isArray(previewResult.value.errors) && previewResult.value.errors.length > 0) return false;
    return canPreview.value;
});

const close = () => {
    emit("update:visible", false);
};

const reset = () => {
    newSupervisorEmployeeId.value = null;
    targetSupervisorForSubordinates.value = null;
    subordinatesStrategy.value = "reassign_to_old_supervisor";
    effectiveFrom.value = new Date();
    previewResult.value = null;
    formError.value = "";
};

const payload = () => ({
    company_id: Number(props.companyId),
    employee_id: Number(props.employeeId),
    new_supervisor_employee_id: needsNewSupervisor.value ? Number(newSupervisorEmployeeId.value) : null,
    mode: props.mode,
    subordinates_strategy: subordinatesStrategy.value,
    target_supervisor_for_subordinates: needsTargetSupervisor.value ? Number(targetSupervisorForSubordinates.value) : null,
    effective_from: effectiveFromYmd.value,
    at_date: effectiveFromYmd.value,
});

const runPreview = async () => {
    if (!canPreview.value) {
        previewResult.value = null;
        return;
    }

    previewLoading.value = true;
    formError.value = "";
    try {
        const params = new URLSearchParams();
        Object.entries(payload()).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== "") {
                params.set(key, String(value));
            }
        });

        const response = await csrfFetch(`${route("org.hierarchy.move.preview")}?${params.toString()}`, {
            method: "GET",
            headers: { Accept: "application/json" },
        });
        const body = await response.json();
        if (!response.ok) {
            throw new Error(body?.message || "Előnézet lekérése sikertelen.");
        }

        previewResult.value = body?.data ?? null;
    } catch (error) {
        previewResult.value = null;
        formError.value = error instanceof Error ? error.message : "Előnézet lekérése sikertelen.";
    } finally {
        previewLoading.value = false;
    }
};

const runExecute = async () => {
    if (!canExecute.value) return;

    executeLoading.value = true;
    formError.value = "";
    try {
        const response = await csrfFetch(route("org.hierarchy.move"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify(payload()),
        });
        const body = await response.json();
        if (!response.ok) {
            throw new Error(body?.message || "Áthelyezés sikertelen.");
        }

        emit("moved", body?.data ?? null);
        close();
    } catch (error) {
        formError.value = error instanceof Error ? error.message : "Áthelyezés sikertelen.";
    } finally {
        executeLoading.value = false;
    }
};

const queuePreview = () => {
    if (previewTimer !== null) {
        clearTimeout(previewTimer);
    }

    previewTimer = setTimeout(() => {
        runPreview();
    }, 300);
};

watch(
    () => props.visible,
    (opened) => {
        if (opened) {
            reset();
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
        props.visible,
        props.companyId,
        props.employeeId,
        props.mode,
        newSupervisorEmployeeId.value,
        targetSupervisorForSubordinates.value,
        subordinatesStrategy.value,
        effectiveFromYmd.value,
    ],
    () => {
        if (!props.visible) return;
        queuePreview();
    },
);
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        :draggable="false"
        :style="{ width: '40rem', maxWidth: '96vw' }"
        :header="modeLabel"
        @update:visible="emit('update:visible', $event)"
    >
        <div class="space-y-4">
            <div v-if="needsNewSupervisor">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Új felettes</label>
                <EmployeeSelector
                    v-model="newSupervisorEmployeeId"
                    :company-id="companyId"
                    :server-search="false"
                    :filter="true"
                    :disabled="loading || previewLoading || executeLoading"
                    placeholder="Vezető kiválasztása"
                />
            </div>

            <div v-if="mode === 'leader_without_subordinates'">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Beosztott stratégia</label>
                <Select
                    v-model="subordinatesStrategy"
                    :options="strategyOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                    :disabled="loading || previewLoading || executeLoading"
                />
            </div>

            <div v-if="needsTargetSupervisor">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Beosztottak célvezetője</label>
                <EmployeeSelector
                    v-model="targetSupervisorForSubordinates"
                    :company-id="companyId"
                    :server-search="false"
                    :filter="true"
                    :disabled="loading || previewLoading || executeLoading"
                    placeholder="Célvezető kiválasztása"
                />
            </div>

            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Hatálybalépés</label>
                <DatePicker
                    v-model="effectiveFrom"
                    dateFormat="yy-mm-dd"
                    showIcon
                    class="w-full"
                    :disabled="loading || previewLoading || executeLoading"
                />
            </div>

            <Message v-if="formError" severity="error" :closable="false">{{ formError }}</Message>

            <div class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm">
                <div class="font-semibold text-slate-700">Előnézet</div>
                <div v-if="previewLoading" class="text-slate-500">Számolás folyamatban...</div>
                <template v-else-if="previewResult">
                    <div class="mt-1 text-slate-700">Érintett dolgozók: <strong>{{ previewResult.affected_count }}</strong></div>
                    <ul v-if="previewResult.warnings?.length" class="mt-2 list-disc pl-5 text-amber-700">
                        <li v-for="(warning, idx) in previewResult.warnings" :key="`w-${idx}`">{{ warning }}</li>
                    </ul>
                    <ul v-if="previewResult.errors?.length" class="mt-2 list-disc pl-5 text-rose-700">
                        <li v-for="(error, idx) in previewResult.errors" :key="`e-${idx}`">{{ error }}</li>
                    </ul>
                </template>
                <div v-else class="mt-1 text-slate-500">Töltsd ki a mezőket az előnézethez.</div>
            </div>
        </div>

        <template #footer>
            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    label="Mégse"
                    severity="secondary"
                    :disabled="executeLoading"
                    @click="close"
                />
                <Button
                    label="Előnézet"
                    icon="pi pi-eye"
                    :loading="previewLoading"
                    :disabled="!canPreview || executeLoading"
                    @click="runPreview"
                />
                <Button
                    label="Végrehajtás"
                    icon="pi pi-check"
                    :loading="executeLoading"
                    :disabled="!canExecute || previewLoading"
                    @click="runExecute"
                />
            </div>
        </template>
    </Dialog>
</template>
