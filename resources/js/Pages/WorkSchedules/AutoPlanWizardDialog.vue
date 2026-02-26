<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import MultiSelect from "primevue/multiselect";
import InputNumber from "primevue/inputnumber";
import Divider from "primevue/divider";
import AutoPlanService from "@/services/AutoPlanService.js";
import EmployeeService from "@/services/EmployeeService.js";
import WorkShiftService from "@/services/WorkShiftService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    defaultMonth: { type: String, default: "" },
});

const emit = defineEmits(["update:modelValue", "generated"]);
const page = usePage();

const currentCompanyId = computed(() => {
    const raw = page?.props?.companyContext?.current_company_id;
    const id = Number(raw ?? 0);
    return Number.isFinite(id) ? id : 0;
});

const demandVisible = ref(false);
const scopeVisible = ref(false);
// Belső modal-váltásnál ne fusson le a teljes wizard bezárás.
const suppressHideClose = ref(false);

const loading = ref(false);
const loadingEligible = ref(false);
const errorText = ref("");
const generated = ref(null);

const month = ref(props.defaultMonth || new Date().toISOString().slice(0, 7));
const dateFrom = ref("");
const dateTo = ref("");
const requiredDailyMinutes = ref(480);
const selectedEmployeeIds = ref([]);

const shiftOptions = ref([]);
const eligibleEmployeeOptions = ref([]);
const eligibleMeta = ref({
    total_employees: 0,
    eligible_count: 0,
    excluded_count: 0,
    excluded_reasons: {
        inactive: 0,
        missing_pattern: 0,
        not_matching_minutes: 0,
    },
    required_daily_minutes: 480,
    month: null,
    date_from: null,
    date_to: null,
});

const weekdayDemand = ref([{ shift_id: null, required_count: 1 }]);
const weekendDemand = ref([{ shift_id: null, required_count: 1 }]);

const defaults = ref({
    min_rest_hours: 11,
    max_consecutive_days: 6,
    weekend_fairness: true,
    allowed_weekdays: [1, 2, 3, 4, 5, 6, 7],
    weekend_policy: "require_if_demand",
});

const monthRegex = /^\d{4}-(0[1-9]|1[0-2])$/;

const hasValidDemand = (items) =>
    items.length > 0 &&
    items.every((x) => Number(x.shift_id) > 0 && Number(x.required_count) >= 1);

const canContinueToScope = computed(() => hasValidDemand(weekdayDemand.value));

const canGenerate = computed(() => {
    return (
        monthRegex.test(month.value) &&
        selectedEmployeeIds.value.length > 0 &&
        hasValidDemand(weekdayDemand.value)
    );
});

const eligibleSummaryText = computed(() => {
    const eligible = Number(eligibleMeta.value?.eligible_count ?? 0);
    const excluded = Number(eligibleMeta.value?.excluded_count ?? 0);
    return `${eligible} elérhető, ${excluded} kizárva`;
});

const excludedBreakdownTitle = computed(() => {
    const inactive = Number(eligibleMeta.value?.excluded_reasons?.inactive ?? 0);
    const missing = Number(eligibleMeta.value?.excluded_reasons?.missing_pattern ?? 0);
    const notMatching = Number(eligibleMeta.value?.excluded_reasons?.not_matching_minutes ?? 0);
    return `Kizárás okai: inaktív: ${inactive}, nincs átfedő munkarend: ${missing}, nem megfelelő percek: ${notMatching}`;
});

const addDemandRow = (bucket) => {
    bucket.push({ shift_id: null, required_count: 1 });
};

const removeDemandRow = (bucket, idx) => {
    if (bucket.length <= 1) return;
    bucket.splice(idx, 1);
};

const normalizeDemand = (items) => {
    const grouped = new Map();
    for (const item of items) {
        const shiftId = Number(item.shift_id || 0);
        const required = Number(item.required_count || 0);
        if (!shiftId || required < 1) continue;
        grouped.set(shiftId, (grouped.get(shiftId) || 0) + required);
    }

    return Array.from(grouped.entries()).map(([shift_id, required_count]) => ({
        shift_id,
        required_count,
    }));
};

const collectDemandShiftIds = () => {
    const weekday = normalizeDemand(weekdayDemand.value);
    const weekend = normalizeDemand(weekendDemand.value);
    const ids = [...weekday, ...weekend].map((x) => Number(x.shift_id));
    return [...new Set(ids)].filter((x) => Number.isInteger(x) && x > 0);
};

const buildPayload = () => ({
    month: month.value,
    employee_ids: selectedEmployeeIds.value.map((x) => Number(x)),
    demand: {
        weekday: normalizeDemand(weekdayDemand.value),
        weekend: normalizeDemand(weekendDemand.value),
    },
});

const syncDateRangeFromMonth = () => {
    if (!monthRegex.test(month.value)) {
        return;
    }

    const [yearRaw, monthRaw] = month.value.split("-");
    const year = Number(yearRaw);
    const mon = Number(monthRaw);
    if (!Number.isInteger(year) || !Number.isInteger(mon)) {
        return;
    }

    const start = new Date(Date.UTC(year, mon - 1, 1));
    const end = new Date(Date.UTC(year, mon, 0));
    dateFrom.value = start.toISOString().slice(0, 10);
    dateTo.value = end.toISOString().slice(0, 10);
};

const resetState = () => {
    errorText.value = "";
    generated.value = null;
    month.value = props.defaultMonth || new Date().toISOString().slice(0, 7);
    syncDateRangeFromMonth();
    requiredDailyMinutes.value = 480;
    selectedEmployeeIds.value = [];
    weekdayDemand.value = [{ shift_id: null, required_count: 1 }];
    weekendDemand.value = [{ shift_id: null, required_count: 1 }];
    eligibleEmployeeOptions.value = [];
    eligibleMeta.value = {
        total_employees: 0,
        eligible_count: 0,
        excluded_count: 0,
        excluded_reasons: {
            inactive: 0,
            missing_pattern: 0,
            not_matching_minutes: 0,
        },
        required_daily_minutes: 480,
        month: null,
        date_from: null,
        date_to: null,
    };
};

const closeAll = () => {
    demandVisible.value = false;
    scopeVisible.value = false;
    emit("update:modelValue", false);
};

const openDemandModal = () => {
    suppressHideClose.value = true;
    demandVisible.value = true;
    scopeVisible.value = false;
    emit("update:modelValue", true);
    nextTick(() => {
        suppressHideClose.value = false;
    });
};

const openScopeModal = () => {
    suppressHideClose.value = true;
    scopeVisible.value = true;
    demandVisible.value = false;
    emit("update:modelValue", true);
    nextTick(() => {
        suppressHideClose.value = false;
    });
};

const onDialogHide = () => {
    // Ha csak lépést váltunk (1/2 -> 2/2), ne zárjuk az egész flow-t.
    if (suppressHideClose.value) {
        return;
    }

    closeAll();
};

const loadShiftSelector = async () => {
    const companyId = Number(currentCompanyId.value || 0);
    const shifts = await WorkShiftService.getToSelect({ company_id: companyId, only_active: 1 }).catch(
        () => ({ data: [] }),
    );

    const raw = Array.isArray(shifts.data) ? shifts.data : [];
    const byId = new Map();
    for (const row of raw) {
        const id = Number(row?.id || 0);
        if (id > 0 && !byId.has(id)) {
            byId.set(id, { id, name: row?.name ?? `Műszak #${id}` });
        }
    }

    shiftOptions.value = Array.from(byId.values());
};

const loadDefaults = async () => {
    try {
        const { data } = await AutoPlanService.getDefaults();
        defaults.value = {
            min_rest_hours: Number(data?.data?.min_rest_hours ?? 11),
            max_consecutive_days: Number(data?.data?.max_consecutive_days ?? 6),
            weekend_fairness: !!data?.data?.weekend_fairness,
            allowed_weekdays: Array.isArray(data?.data?.allowed_weekdays)
                ? data.data.allowed_weekdays.map((x) => Number(x)).filter((x) => x >= 1 && x <= 7)
                : [1, 2, 3, 4, 5, 6, 7],
            weekend_policy: String(data?.data?.weekend_policy ?? "require_if_demand"),
        };
    } catch (_) {
        defaults.value = {
            min_rest_hours: 11,
            max_consecutive_days: 6,
            weekend_fairness: true,
            allowed_weekdays: [1, 2, 3, 4, 5, 6, 7],
            weekend_policy: "require_if_demand",
        };
    }
};

const fetchEligibleEmployees = async () => {
    loadingEligible.value = true;
    errorText.value = "";

    try {
        const shiftIds = collectDemandShiftIds();
        const { data } = await EmployeeService.getEligibleForAutoPlan({
            month: month.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            shift_ids: shiftIds,
            required_daily_minutes: Number(requiredDailyMinutes.value || 480),
        });

        const list = Array.isArray(data?.data) ? data.data : [];
        const meta = data?.meta && typeof data.meta === "object" ? data.meta : null;

        eligibleEmployeeOptions.value = list;
        if (meta) {
            eligibleMeta.value = {
                total_employees: Number(meta.total_employees ?? meta.total_count ?? 0),
                eligible_count: Number(meta.eligible_count ?? 0),
                excluded_count: Number(meta.excluded_count ?? 0),
                excluded_reasons: {
                    inactive: Number(meta?.excluded_reasons?.inactive ?? meta?.breakdown?.inactive ?? 0),
                    missing_pattern: Number(meta?.excluded_reasons?.missing_pattern ?? 0),
                    not_matching_minutes: Number(meta?.excluded_reasons?.not_matching_minutes ?? meta?.breakdown?.not_target_daily_minutes ?? 0),
                },
                required_daily_minutes: Number(meta.required_daily_minutes ?? meta.target_daily_minutes ?? 480),
                month: meta.month ?? null,
                date_from: meta.date_from ?? dateFrom.value,
                date_to: meta.date_to ?? dateTo.value,
            };
        }

        // Ha a filter változik, maradjanak csak még mindig jogosult kiválasztások.
        const eligibleIds = new Set(list.map((x) => Number(x.id)));
        selectedEmployeeIds.value = selectedEmployeeIds.value.filter((id) => eligibleIds.has(Number(id)));
    } catch (e) {
        eligibleEmployeeOptions.value = [];
        errorText.value =
            e?.response?.data?.message || e?.message || "A jogosult dolgozók lekérése sikertelen.";
    } finally {
        loadingEligible.value = false;
    }
};

const continueToScope = async () => {
    if (!canContinueToScope.value) {
        errorText.value = "A létszámigény kitöltése kötelező.";
        return;
    }

    generated.value = null;
    await fetchEligibleEmployees();
    openScopeModal();
};

const backToDemand = () => {
    openDemandModal();
};

const generate = async () => {
    if (!canGenerate.value) return;

    loading.value = true;
    errorText.value = "";

    try {
        const payload = buildPayload();
        const { data } = await AutoPlanService.generate(payload);
        generated.value = data?.data ?? null;
        emit("generated", generated.value);
    } catch (e) {
        const validationErrors = e?.response?.data?.errors;
        if (validationErrors && typeof validationErrors === "object") {
            errorText.value = Object.values(validationErrors)
                .flat()
                .filter(Boolean)
                .join(" | ");
            return;
        }

        errorText.value = e?.response?.data?.message || e?.message || "AutoPlan hiba történt.";
    } finally {
        loading.value = false;
    }
};

const applyAndOpen = () => {
    const scheduleId = Number(generated.value?.work_schedule?.id || 0);
    if (!scheduleId) return;
    closeAll();
    window.location.href = `/scheduling/calendar?schedule_id=${scheduleId}`;
};

watch(
    () => props.modelValue,
    (isOpen) => {
        if (isOpen) {
            resetState();
            openDemandModal();
            loadDefaults();
            return;
        }

        demandVisible.value = false;
        scopeVisible.value = false;
    },
);

watch(
    () => month.value,
    () => {
        syncDateRangeFromMonth();
    },
);

watch(
    () => [month.value, dateFrom.value, dateTo.value, requiredDailyMinutes.value],
    () => {
        if (!scopeVisible.value) return;
        if (!monthRegex.test(month.value)) return;
        fetchEligibleEmployees();
    },
);

onMounted(async () => {
    await Promise.all([loadShiftSelector(), loadDefaults()]);
});
</script>

<template>
    <Dialog
        v-model:visible="demandVisible"
        modal
        header="AutoPlan - 1/2: Létszámigény"
        :style="{ width: 'min(980px, 95vw)' }"
        :draggable="false"
        @hide="onDialogHide"
    >
        <div class="space-y-4">
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                Add meg a hétköznapi és hétvégi létszámigényt. A következő lépésben csak ezekhez a feltételekhez
                illeszkedő dolgozók közül lehet választani.
            </div>

            <div class="rounded-lg border border-slate-200 p-3">
                <div class="mb-2 text-sm font-medium">Tervezési szabályok (alkalmazás beállítások)</div>
                <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                    <div>Minimum pihenőidő: <b>{{ defaults.min_rest_hours }} óra</b></div>
                    <div>Max egymást követő nap: <b>{{ defaults.max_consecutive_days }} nap</b></div>
                    <div>Hétvégi arányosság: <b>{{ defaults.weekend_fairness ? "Bekapcsolva" : "Kikapcsolva" }}</b></div>
                    <div>Hétvége policy: <b>{{ defaults.weekend_policy }}</b></div>
                    <div class="md:col-span-2 xl:col-span-4">
                        Tervezhető napok (ISO): <b>{{ (defaults.allowed_weekdays || []).join(", ") }}</b>
                    </div>
                    <div class="min-w-0 rounded border border-slate-200 p-2">
                        <label class="mb-1 block text-xs text-slate-600">Elvárt napi munkaidő</label>
                        <div class="flex flex-wrap items-center gap-2">
                            <InputNumber
                                v-model="requiredDailyMinutes"
                                :min="1"
                                :max="1440"
                                class="w-full sm:w-40"
                                inputClass="w-full"
                            />
                            <span class="text-xs text-slate-500">perc/nap</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="font-medium">Hétköznapi igény</div>
                        <Button label="+ Sor" size="small" text @click="addDemandRow(weekdayDemand)" />
                    </div>

                    <div
                        v-for="(row, idx) in weekdayDemand"
                        :key="`wd-${idx}`"
                        class="mb-2 grid grid-cols-[1fr_140px_70px] gap-2"
                    >
                        <Select
                            v-model="row.shift_id"
                            :options="shiftOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Műszak"
                            class="w-full"
                        />
                        <InputNumber v-model="row.required_count" :min="1" :max="500" class="w-full" />
                        <Button icon="pi pi-trash" severity="danger" text @click="removeDemandRow(weekdayDemand, idx)" />
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="mb-3 flex items-center justify-between">
                        <div class="font-medium">Hétvégi igény (opcionális)</div>
                        <Button label="+ Sor" size="small" text @click="addDemandRow(weekendDemand)" />
                    </div>

                    <div
                        v-for="(row, idx) in weekendDemand"
                        :key="`we-${idx}`"
                        class="mb-2 grid grid-cols-[1fr_140px_70px] gap-2"
                    >
                        <Select
                            v-model="row.shift_id"
                            :options="shiftOptions"
                            optionLabel="name"
                            optionValue="id"
                            placeholder="Műszak"
                            class="w-full"
                        />
                        <InputNumber v-model="row.required_count" :min="1" :max="500" class="w-full" />
                        <Button icon="pi pi-trash" severity="danger" text @click="removeDemandRow(weekendDemand, idx)" />
                    </div>
                </div>
            </div>

            <div v-if="errorText" class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ errorText }}
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <Button label="Mégse" severity="secondary" outlined :disabled="loadingEligible" @click="closeAll" />
                <Button
                    label="Tovább: Időszak és dolgozók"
                    icon="pi pi-arrow-right"
                    :loading="loadingEligible"
                    :disabled="!canContinueToScope || loadingEligible"
                    @click="continueToScope"
                />
            </div>
        </div>
    </Dialog>

    <Dialog
        v-model:visible="scopeVisible"
        modal
        header="AutoPlan - 2/2: Időszak és dolgozók"
        :style="{ width: 'min(980px, 95vw)' }"
        :draggable="false"
        @hide="onDialogHide"
    >
        <div class="space-y-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-600">Hónap (YYYY-MM)</label>
                    <InputText v-model="month" class="w-full" placeholder="2026-03" />
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-600">Időszak</label>
                    <div class="grid grid-cols-2 gap-2">
                        <InputText v-model="dateFrom" class="w-full" placeholder="2026-03-01" />
                        <InputText v-model="dateTo" class="w-full" placeholder="2026-03-31" />
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-xs text-slate-600">Dolgozók</label>
                    <MultiSelect
                        v-model="selectedEmployeeIds"
                        :options="eligibleEmployeeOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                        :loading="loadingEligible"
                        placeholder="Válassz jogosult dolgozókat"
                    />
                </div>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                <div>
                    <span class="font-medium">{{ eligibleSummaryText }}</span>
                    <span class="ml-2 text-slate-600">(8 órás, aktív dolgozók a kiválasztott cégből)</span>
                </div>
                <span class="cursor-help text-slate-500 underline decoration-dotted" :title="excludedBreakdownTitle">
                    kizárás részletei
                </span>
            </div>

            <div v-if="generated" class="space-y-3">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm">
                    <div class="font-semibold text-emerald-800">Generálás kész</div>
                    <div class="text-emerald-700">
                        Draft azonosító: <b>{{ generated?.work_schedule?.id }}</b>,
                        név: <b>{{ generated?.work_schedule?.name }}</b>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div class="rounded border p-3">
                        <div class="text-xs text-slate-500">Összes slot</div>
                        <div class="text-lg font-semibold">{{ generated?.coverage?.slots_total ?? 0 }}</div>
                    </div>
                    <div class="rounded border p-3">
                        <div class="text-xs text-slate-500">Lefedett</div>
                        <div class="text-lg font-semibold">{{ generated?.coverage?.slots_filled ?? 0 }}</div>
                    </div>
                    <div class="rounded border p-3">
                        <div class="text-xs text-slate-500">Hiány</div>
                        <div class="text-lg font-semibold text-amber-700">{{ generated?.coverage?.slots_missing ?? 0 }}</div>
                    </div>
                    <div class="rounded border p-3">
                        <div class="text-xs text-slate-500">Lefedettség</div>
                        <div class="text-lg font-semibold">{{ generated?.coverage?.coverage_rate ?? 0 }}%</div>
                    </div>
                </div>

                <Divider />

                <div>
                    <div class="mb-2 text-sm font-medium">Hiánylista (első 30)</div>
                    <div v-if="!(generated?.missing?.length)" class="text-sm text-slate-600">Nincs hiány.</div>
                    <div v-else class="max-h-60 overflow-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">Dátum</th>
                                    <th class="px-3 py-2">Műszak</th>
                                    <th class="px-3 py-2">Ok</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, idx) in (generated?.missing ?? []).slice(0, 30)"
                                    :key="`m-${idx}`"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2">{{ row.shift_id }}</td>
                                    <td class="px-3 py-2">{{ row.reason }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="errorText" class="rounded border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                {{ errorText }}
            </div>

            <div class="flex items-center justify-between pt-2">
                <Button label="Vissza a létszámigényhez" severity="secondary" text :disabled="loading" @click="backToDemand" />
                <div class="flex items-center gap-2">
                    <Button label="Mégse" severity="secondary" outlined :disabled="loading" @click="closeAll" />
                    <Button
                        label="Generálás és előnézet"
                        icon="pi pi-cog"
                        :loading="loading"
                        :disabled="!canGenerate || loading"
                        @click="generate"
                    />
                    <Button
                        label="Draft megnyitás"
                        icon="pi pi-arrow-right"
                        :disabled="!generated?.work_schedule?.id"
                        @click="applyAndOpen"
                    />
                </div>
            </div>
        </div>
    </Dialog>
</template>
