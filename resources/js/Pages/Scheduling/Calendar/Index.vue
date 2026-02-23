<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from "primevue/button";
import Select from "primevue/select";
import SelectButton from "primevue/selectbutton";
import MultiSelect from "primevue/multiselect";
import ToggleSwitch from "primevue/toggleswitch";
import InputNumber from "primevue/inputnumber";
import DatePicker from "primevue/datepicker";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";

import CalendarBoard from "@/Pages/Scheduling/Calendar/Partials/CalendarBoard.vue";
import AssignmentCreateModal from "@/Pages/Scheduling/Calendar/AssignmentCreateModal.vue";
import AssignmentEditModal from "@/Pages/Scheduling/Calendar/AssignmentEditModal.vue";
import AssignmentBulkAssignModal from "@/Pages/Scheduling/Calendar/AssignmentBulkAssignModal.vue";

import EmployeeService from "@/services/EmployeeService.js";
import WorkShiftService from "@/services/WorkShiftService.js";
import PositionService from "@/services/PositionService.js";
import WorkScheduleAssignmentService from "@/services/WorkScheduleAssignmentService.js";

const props = defineProps({
    title: { type: String, default: "Naptár tervező" },
    current_company_id: { type: Number, required: true },
    schedules: { type: Array, default: () => [] },
    permissions: {
        type: Object,
        default: () => ({ viewer: false, planner: false }),
    },
});

const toast = useToast();

const queryScheduleId = Number(new URLSearchParams(window.location.search).get("schedule_id") || 0);

const initialScheduleId = (() => {
    if (queryScheduleId > 0 && props.schedules.some((x) => Number(x?.id) === queryScheduleId)) {
        return queryScheduleId;
    }

    const preferred = props.schedules.find((x) => String(x?.status) !== "published") ?? props.schedules?.[0];
    return Number(preferred?.id ?? 0) || null;
})();

const scheduleId = ref(initialScheduleId);
const viewMode = ref("week");
const month = ref(new Date().getMonth() + 1);
const year = ref(new Date().getFullYear());
const dayDate = ref(new Date());
const plannerMode = ref(false);
const selectedEmployeeIds = ref([]);
const selectedShiftIds = ref([]);
const selectedPositionIds = ref([]);
const selectedDates = ref([]);
const loading = ref(false);
const events = ref([]);
const feedMeta = ref({
    range: { start: null, end: null },
    selected_date: null,
    editable: false,
});

const createOpen = ref(false);
const editOpen = ref(false);
const bulkOpen = ref(false);
const createDate = ref(null);
const selectedEvent = ref(null);
const activeQuickSelect = ref(null);

const employeeOptions = ref([]);
const shiftOptions = ref([]);
const positionOptions = ref([]);

const canPlanner = computed(() => !!props.permissions?.planner);
const selectedSchedule = computed(() =>
    props.schedules.find((x) => Number(x.id) === Number(scheduleId.value)) ?? null
);

const monthOptions = [
    { label: "Január", value: 1 },
    { label: "Február", value: 2 },
    { label: "Március", value: 3 },
    { label: "Április", value: 4 },
    { label: "Május", value: 5 },
    { label: "Június", value: 6 },
    { label: "Július", value: 7 },
    { label: "Augusztus", value: 8 },
    { label: "Szeptember", value: 9 },
    { label: "Október", value: 10 },
    { label: "November", value: 11 },
    { label: "December", value: 12 },
];

const viewModeOptions = [
    { label: "Heti", value: "week" },
    { label: "Havi", value: "month" },
    { label: "Napi", value: "day" },
];

const weeklyQuickSelectOptions = [
    { label: "H-P", value: "workdays" },
    { label: "Szo-V", value: "weekends" },
    { label: "Osszes", value: "all" },
    { label: "Paratlan", value: "odd" },
    { label: "Paros", value: "even" },
];

const monthlyQuickSelectOptions = [
    { label: "H-P", value: "workdays" },
    { label: "Szo-V", value: "weekends" },
    { label: "Osszes", value: "all" },
    { label: "Paratlan", value: "odd" },
    { label: "Paros", value: "even" },
    { label: "Aktualis het", value: "current_week" },
    { label: "Paros het", value: "even_week" },
    { label: "Paratlan het", value: "odd_week" },
    { label: "Mai nap", value: "today" },
    { label: "Kovetkezo het", value: "next_week" },
    { label: "Honap hatralevo", value: "month_remaining" },
    { label: "Clear", value: "clear" },
    { label: "Invert", value: "invert" },
];

const quickSelectOptions = computed(() => {
    if (viewMode.value === "month") return monthlyQuickSelectOptions;
    if (viewMode.value === "week") return weeklyQuickSelectOptions;
    return [];
});

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 11 }, (_, i) => {
        const value = currentYear - 5 + i;
        return { label: String(value), value };
    });
});

const getIsoWeek = (value) => {
    const date = new Date(Date.UTC(value.getFullYear(), value.getMonth(), value.getDate()));
    const day = date.getUTCDay() || 7;
    date.setUTCDate(date.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil((((date - yearStart) / 86400000) + 1) / 7);
};

const getIsoWeeksInYear = (isoYear) => getIsoWeek(new Date(isoYear, 11, 28));

const getIsoWeekStart = (isoYear, isoWeek) => {
    const jan4 = new Date(Date.UTC(isoYear, 0, 4));
    const jan4Day = jan4.getUTCDay() || 7;
    const monday = new Date(jan4);
    monday.setUTCDate(jan4.getUTCDate() - jan4Day + 1 + (isoWeek - 1) * 7);
    return new Date(monday.getUTCFullYear(), monday.getUTCMonth(), monday.getUTCDate());
};

const currentIsoWeek = getIsoWeek(new Date());
const weekNumber = ref(currentIsoWeek);
const weekNumberMax = computed(() => getIsoWeeksInYear(Number(year.value)));

const todayYmd = computed(() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
        d.getDate(),
    ).padStart(2, "0")}`;
});

const selectedDateYmd = computed(() => {
    if (viewMode.value === "day") {
        const d = dayDate.value instanceof Date ? dayDate.value : new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
            d.getDate(),
        ).padStart(2, "0")}`;
    }

    if (viewMode.value === "month") {
        const end = new Date(Number(year.value), Number(month.value), 0);
        return `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, "0")}-${String(
            end.getDate(),
        ).padStart(2, "0")}`;
    }

    const resolvedWeek = Math.min(Math.max(1, Number(weekNumber.value || 1)), Number(weekNumberMax.value || 52));
    const start = getIsoWeekStart(Number(year.value), resolvedWeek);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, "0")}-${String(
        end.getDate(),
    ).padStart(2, "0")}`;
});

const selectedPeriodEditable = computed(() => {
    return String(selectedDateYmd.value) >= String(todayYmd.value);
});

const plannerModeEnabled = computed(() => {
    return (
        canPlanner.value &&
        plannerMode.value &&
        selectedPeriodEditable.value &&
        selectedSchedule.value?.status !== "published"
    );
});

const plannerDisabledReason = computed(() => {
    if (!canPlanner.value) return "Nincs tervezési jogosultság.";
    if (selectedSchedule.value?.status === "published") {
        return "A kiválasztott beosztás publikált, ezért nem szerkeszthető. Válassz draft státuszú beosztást.";
    }
    if (!selectedPeriodEditable.value) return "Múltbeli időszak nem szerkeszthető.";
    return "";
});

const scheduleRange = computed(() => ({
    from: feedMeta.value?.range?.start ?? null,
    to: feedMeta.value?.range?.end ?? null,
}));

const anchorDate = computed(() => {
    if (viewMode.value === "day") {
        return dayDate.value instanceof Date ? dayDate.value : new Date();
    }

    if (viewMode.value === "month") {
        return new Date(Number(year.value), Number(month.value) - 1, 1);
    }

    const resolvedWeek = Math.min(Math.max(1, Number(weekNumber.value || 1)), Number(weekNumberMax.value || 52));
    return getIsoWeekStart(Number(year.value), resolvedWeek);
});

const currentRangeLabel = computed(() => {
    const start = scheduleRange.value.from;
    const end = scheduleRange.value.to;
    if (!start || !end) return "-";
    return `${start} - ${end}`;
});

const loadSelectors = async () => {
    const companyId = Number(props.current_company_id ?? 0);
    if (!companyId) return;

    const [employees, shifts, positions] = await Promise.all([
        EmployeeService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
        WorkShiftService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
        PositionService.getToSelect({ company_id: companyId, only_active: 1 }).catch(() => ({ data: [] })),
    ]);

    employeeOptions.value = Array.isArray(employees.data) ? employees.data : [];
    shiftOptions.value = Array.isArray(shifts.data) ? shifts.data : [];
    positionOptions.value = Array.isArray(positions.data) ? positions.data : [];
};

const toYmd = (value) => {
    if (!(value instanceof Date)) return "";
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(
        value.getDate(),
    ).padStart(2, "0")}`;
};

const toDateKey = (value) => {
    if (!value) return "";
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value)) return value;

    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return "";

    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(
        dt.getDate(),
    ).padStart(2, "0")}`;
};

const getDayOfWeek = (value) => {
    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.getDay();
};

const getDayOfMonth = (value) => {
    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return null;
    return dt.getDate();
};

const getIsoWeekMeta = (value) => {
    const dt = value instanceof Date ? new Date(value) : new Date(value);
    if (Number.isNaN(dt.getTime())) return null;

    const utc = new Date(Date.UTC(dt.getFullYear(), dt.getMonth(), dt.getDate()));
    const day = utc.getUTCDay() || 7;
    utc.setUTCDate(utc.getUTCDate() + 4 - day);

    const isoYear = utc.getUTCFullYear();
    const yearStart = new Date(Date.UTC(isoYear, 0, 1));
    const isoWeek = Math.ceil((((utc - yearStart) / 86400000) + 1) / 7);

    return { isoWeek, isoYear };
};

const getIsoWeekNumber = (value) => getIsoWeekMeta(value)?.isoWeek ?? null;

const isSameIsoWeek = (a, b) => {
    const aw = getIsoWeekMeta(a);
    const bw = getIsoWeekMeta(b);
    if (!aw || !bw) return false;
    return aw.isoWeek === bw.isoWeek && aw.isoYear === bw.isoYear;
};

const startOfWeekDate = (value) => {
    const base = value instanceof Date ? new Date(value) : new Date();
    const day = (base.getDay() + 6) % 7;
    base.setDate(base.getDate() - day);
    base.setHours(0, 0, 0, 0);
    return base;
};

const isDateSelectable = (ymd) => {
    if (!ymd) return false;
    if (String(ymd) < String(todayYmd.value)) return false;
    if (scheduleRange.value.from && ymd < scheduleRange.value.from) return false;
    if (scheduleRange.value.to && ymd > scheduleRange.value.to) return false;
    return true;
};

const getVisibleGridDays = () => {
    if (viewMode.value === "day") {
        const key = toDateKey(anchorDate.value);
        if (!key) return [];
        return [new Date(anchorDate.value)];
    }

    if (viewMode.value === "week") {
        const start = startOfWeekDate(anchorDate.value);
        return Array.from({ length: 7 }, (_, index) => {
            const date = new Date(start);
            date.setDate(start.getDate() + index);
            return date;
        });
    }

    if (viewMode.value === "month") {
        const first = new Date(Number(year.value), Number(month.value) - 1, 1);
        const start = startOfWeekDate(first);
        return Array.from({ length: 42 }, (_, index) => {
            const date = new Date(start);
            date.setDate(start.getDate() + index);
            return date;
        });
    }

    return [];
};

const buildQuickSelectTargetKeys = (type) => {
    const visible = getVisibleGridDays()
        .map((date) => ({ date, dateKey: toDateKey(date) }))
        .filter((day) => !!day.dateKey && isDateSelectable(day.dateKey));

    if (!visible.length) return [];

    const today = new Date();
    const todayPlusSeven = new Date(today);
    todayPlusSeven.setDate(todayPlusSeven.getDate() + 7);

    const currentMonthIndex = Number(month.value) - 1;
    const currentMonthYear = Number(year.value);

    const matched = visible.filter((day) => {
        const dayOfWeek = getDayOfWeek(day.date);
        const dayOfMonth = getDayOfMonth(day.date);
        const isoWeek = getIsoWeekNumber(day.date);

        if (type === "workdays") return dayOfWeek !== null && dayOfWeek >= 1 && dayOfWeek <= 5;
        if (type === "weekends") return dayOfWeek === 0 || dayOfWeek === 6;
        if (type === "all") return true;
        if (type === "odd") return dayOfMonth !== null && dayOfMonth % 2 === 1;
        if (type === "even") return dayOfMonth !== null && dayOfMonth % 2 === 0;
        if (type === "current_week") return isSameIsoWeek(day.date, today);
        if (type === "even_week") return isoWeek !== null && isoWeek % 2 === 0;
        if (type === "odd_week") return isoWeek !== null && isoWeek % 2 === 1;
        if (type === "today") return day.dateKey === todayYmd.value;
        if (type === "next_week") return isSameIsoWeek(day.date, todayPlusSeven);
        if (type === "month_remaining") {
            return (
                day.dateKey >= todayYmd.value &&
                day.date.getFullYear() === currentMonthYear &&
                day.date.getMonth() === currentMonthIndex
            );
        }

        return false;
    });

    return matched.map((day) => day.dateKey);
};

const applyQuickSelect = (type, mouseEvent) => {
    const additive = !!(mouseEvent?.ctrlKey || mouseEvent?.metaKey);
    const subtractive = !!mouseEvent?.altKey;

    const visibleSelectableKeys = new Set(
        getVisibleGridDays()
            .map((date) => toDateKey(date))
            .filter((key) => !!key && isDateSelectable(key)),
    );

    if (!visibleSelectableKeys.size) {
        selectedDates.value = [];
        activeQuickSelect.value = null;
        return;
    }

    if (type === "clear") {
        selectedDates.value = [];
        activeQuickSelect.value = "clear";
        return;
    }

    if (type === "invert") {
        const current = new Set(selectedDates.value);
        const inverted = [];

        for (const key of visibleSelectableKeys) {
            if (!current.has(key)) inverted.push(key);
        }

        selectedDates.value = inverted.sort();
        activeQuickSelect.value = "invert";
        return;
    }

    if (!additive && !subtractive && activeQuickSelect.value === type) {
        selectedDates.value = [];
        activeQuickSelect.value = null;
        return;
    }

    const targetKeys = buildQuickSelectTargetKeys(type);
    const targetSet = new Set(targetKeys);

    if (subtractive) {
        selectedDates.value = selectedDates.value.filter((key) => !targetSet.has(key));
    } else if (additive) {
        selectedDates.value = Array.from(new Set([...selectedDates.value, ...targetKeys])).sort();
    } else {
        selectedDates.value = [...targetKeys].sort();
    }

    activeQuickSelect.value = type;
};

const syncSelectionWithVisibleRange = () => {
    if (viewMode.value !== "week" && viewMode.value !== "month") return;

    const visibleKeys = new Set(getVisibleGridDays().map((date) => toDateKey(date)).filter(Boolean));
    const next = selectedDates.value.filter((key) => visibleKeys.has(key));

    if (next.length !== selectedDates.value.length) {
        selectedDates.value = next;
        activeQuickSelect.value = null;
    }
};

const buildFeedParams = () => {
    const params = {
        schedule_id: Number(scheduleId.value),
        view_type: viewMode.value,
        employee_ids: selectedEmployeeIds.value,
        work_shift_ids: selectedShiftIds.value,
        position_ids: selectedPositionIds.value,
    };

    if (viewMode.value === "week") {
        const resolvedWeek = Math.min(Math.max(1, Number(weekNumber.value || 1)), Number(weekNumberMax.value || 52));
        params.week_number = resolvedWeek;
        params.week_year = Number(year.value);
    } else if (viewMode.value === "month") {
        params.month = Number(month.value);
        params.year = Number(year.value);
    } else {
        params.date = toYmd(dayDate.value);
    }

    return params;
};

const loadEvents = async () => {
    if (!scheduleId.value) {
        events.value = [];
        feedMeta.value = {
            range: { start: null, end: null },
            selected_date: null,
            editable: false,
        };
        return;
    }

    loading.value = true;
    try {
        const { data } = await WorkScheduleAssignmentService.getCalendarFeed(buildFeedParams());
        events.value = Array.isArray(data?.data) ? data.data : [];
        feedMeta.value = {
            range: data?.meta?.range ?? { start: null, end: null },
            selected_date: data?.meta?.selected_date ?? null,
            editable: !!data?.meta?.editable,
        };

        if (!selectedPeriodEditable.value || selectedSchedule.value?.status === "published") {
            plannerMode.value = false;
        }
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Naptár feed hiba.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const refresh = async () => {
    selectedDates.value = [];
    activeQuickSelect.value = null;
    await loadEvents();
};

const resetViewFilters = () => {
    const now = new Date();
    if (viewMode.value === "week") {
        year.value = now.getFullYear();
        weekNumber.value = getIsoWeek(now);
    } else if (viewMode.value === "month") {
        month.value = now.getMonth() + 1;
        year.value = now.getFullYear();
    } else {
        dayDate.value = now;
    }
};

const onDateClick = ({ date }) => {
    if (!plannerModeEnabled.value) return;
    if (String(date) < String(todayYmd.value)) return;

    createDate.value = date;
    createOpen.value = true;
};

const onEventClick = (event) => {
    selectedEvent.value = event;
    if (plannerModeEnabled.value && !!event?.editable) {
        editOpen.value = true;
        return;
    }

    toast.add({
        severity: "info",
        summary: "Részletek",
        detail: event.title,
        life: 3000,
    });
};

const handleCreate = async (payload) => {
    try {
        await WorkScheduleAssignmentService.createAssignment(payload);
        createOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés létrehozva.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Mentés sikertelen.",
            life: 3500,
        });
    }
};

const handleUpdate = async ({ id, payload }) => {
    try {
        await WorkScheduleAssignmentService.updateAssignment(id, payload);
        editOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés frissítve.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Frissítés sikertelen.",
            life: 3500,
        });
    }
};

const handleDelete = async (id) => {
    try {
        await WorkScheduleAssignmentService.deleteAssignment(id);
        editOpen.value = false;
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Hozzárendelés törölve.", life: 2200 });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Törlés sikertelen.",
            life: 3500,
        });
    }
};

const onEventDrop = async ({ id, date }) => {
    const row = events.value.find((x) => Number(x.id) === Number(id));
    if (!row || !row.editable) return;

    await handleUpdate({
        id,
        payload: {
            work_schedule_id: Number(row.extendedProps.schedule_id),
            employee_id: Number(row.extendedProps.employee_id),
            work_shift_id: Number(row.extendedProps.shift_id),
            date,
        },
    });
};

const toggleSelectedDate = (ymd) => {
    if (!plannerModeEnabled.value) return;
    if (!isDateSelectable(ymd)) return;

    if (selectedDates.value.includes(ymd)) {
        selectedDates.value = selectedDates.value.filter((x) => x !== ymd);
    } else {
        selectedDates.value = [...selectedDates.value, ymd].sort();
    }

    activeQuickSelect.value = null;
};

const handleBulk = async (payload) => {
    if (!payload.employee_ids?.length) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Válassz legalább egy dolgozót.", life: 3200 });
        return;
    }
    if (!payload.work_shift_id) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Válassz műszakot.", life: 3200 });
        return;
    }
    if (!payload.dates?.length) {
        toast.add({ severity: "warn", summary: "Validáció", detail: "Nincs kijelölt nap.", life: 3200 });
        return;
    }

    try {
        await WorkScheduleAssignmentService.bulkUpsert(payload);
        bulkOpen.value = false;
        selectedDates.value = [];
        await loadEvents();
        toast.add({ severity: "success", summary: "Siker", detail: "Bulk mentés kész.", life: 2200 });
    } catch (e) {
        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === "object") {
            const detail = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" | ");
            toast.add({
                severity: "warn",
                summary: "Validációs hiba",
                detail: detail || "A megadott adatok hibásak.",
                life: 5000,
            });
            return;
        }
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.response?.data?.message || "Bulk mentés sikertelen.",
            life: 3500,
        });
    }
};

watch(
    viewMode,
    async () => {
        resetViewFilters();
        selectedDates.value = [];
        activeQuickSelect.value = null;
        await loadEvents();
    },
    { immediate: false },
);

watch([scheduleId, weekNumber, month, year, dayDate], loadEvents);
watch([selectedEmployeeIds, selectedShiftIds, selectedPositionIds], loadEvents);
watch([weekNumber, month, year, viewMode], syncSelectionWithVisibleRange);

watch(
    [year, viewMode],
    () => {
        if (viewMode.value !== "week") return;
        const maxWeek = Number(weekNumberMax.value || 52);
        if (Number(weekNumber.value) > maxWeek) {
            weekNumber.value = maxWeek;
        }
    },
    { immediate: true },
);

watch(
    selectedSchedule,
    (row) => {
        if (!row) return;
        if (row.status === "published") {
            plannerMode.value = false;
        }
    },
    { immediate: true },
);

onMounted(async () => {
    await loadSelectors();
    await loadEvents();
});
</script>

<template>
    <Head :title="title" />
    <Toast />

    <AssignmentCreateModal
        v-model="createOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :defaultDate="createDate"
        :loading="loading"
        @submit="handleCreate"
    />

    <AssignmentEditModal
        v-model="editOpen"
        :assignment="selectedEvent"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :loading="loading"
        @update="handleUpdate"
        @delete="handleDelete"
    />

    <AssignmentBulkAssignModal
        v-model="bulkOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :selectedDates="selectedDates"
        :loading="loading"
        @submit="handleBulk"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4">
                <div class="min-w-64">
                    <label class="mb-1 block text-xs text-slate-600">Munkabeosztás</label>
                    <Select
                        v-model="scheduleId"
                        :options="schedules"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        placeholder="Válassz beosztást"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs text-slate-600">Nézet</label>
                    <SelectButton
                        v-model="viewMode"
                        :options="viewModeOptions"
                        optionLabel="label"
                        optionValue="value"
                    />
                </div>

                <div v-if="viewMode === 'week'" class="min-w-40">
                    <label class="mb-1 block text-xs text-slate-600">Hét száma (ISO)</label>
                    <InputNumber
                        v-model="weekNumber"
                        :min="1"
                        :max="weekNumberMax"
                        showButtons
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'week'" class="min-w-32">
                    <label class="mb-1 block text-xs text-slate-600">Év</label>
                    <Select
                        v-model="year"
                        :options="yearOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'month'" class="min-w-52">
                    <label class="mb-1 block text-xs text-slate-600">Hónap</label>
                    <Select
                        v-model="month"
                        :options="monthOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'month'" class="min-w-32">
                    <label class="mb-1 block text-xs text-slate-600">Év</label>
                    <Select
                        v-model="year"
                        :options="yearOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'day'" class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Dátum</label>
                    <DatePicker
                        v-model="dayDate"
                        dateFormat="yy-mm-dd"
                        showIcon
                        class="w-full"
                    />
                </div>

                <div class="min-w-64">
                    <label class="mb-1 block text-xs text-slate-600">Dolgozó szűrő</label>
                    <MultiSelect
                        v-model="selectedEmployeeIds"
                        :options="employeeOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <div class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Műszak szűrő</label>
                    <MultiSelect
                        v-model="selectedShiftIds"
                        :options="shiftOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <div class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600">Pozíció szűrő</label>
                    <MultiSelect
                        v-model="selectedPositionIds"
                        :options="positionOptions"
                        optionLabel="name"
                        optionValue="id"
                        class="w-full"
                        display="chip"
                        filter
                    />
                </div>

                <Button icon="pi pi-refresh" severity="secondary" :loading="loading" @click="refresh" />

                <div class="text-xs text-slate-600">
                    Intervallum: <b>{{ currentRangeLabel }}</b>
                </div>

                <div
                    v-if="plannerModeEnabled && (viewMode === 'week' || viewMode === 'month')"
                    class="flex flex-col gap-1"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-600">Gyors kijeloles:</span>
                        <Button
                            v-for="opt in quickSelectOptions"
                            :key="opt.value"
                            :label="opt.label"
                            size="small"
                            :severity="activeQuickSelect === opt.value ? 'primary' : 'secondary'"
                            :outlined="activeQuickSelect !== opt.value"
                            @click="applyQuickSelect(opt.value, $event)"
                        />
                        <span class="text-xs text-slate-600">{{ selectedDates.length }} nap kijelolve</span>
                    </div>
                    <div class="text-[11px] text-slate-500">
                        Modok: kattintas = feluliras, ugyanaz ujra = torles, Ctrl/Cmd + kattintas = hozzaadas, Alt + kattintas = kivonas.
                    </div>
                </div>

                <div v-if="canPlanner" class="ml-auto flex items-center gap-2">
                    <span class="text-sm">Planner mód</span>
                    <ToggleSwitch
                        v-model="plannerMode"
                        :disabled="!selectedPeriodEditable || selectedSchedule?.status === 'published'"
                    />
                </div>

                <div
                    v-if="canPlanner && plannerDisabledReason && (!selectedPeriodEditable || selectedSchedule?.status === 'published')"
                    class="text-xs text-amber-700"
                >
                    {{ plannerDisabledReason }}
                </div>

                <Button
                    v-if="plannerModeEnabled"
                    label="Bulk kijelöltek"
                    icon="pi pi-list-check"
                    :disabled="selectedDates.length === 0"
                    @click="bulkOpen = true"
                />
            </div>

            <CalendarBoard
                :events="events"
                :viewMode="viewMode"
                :anchorDate="anchorDate"
                :plannerMode="plannerModeEnabled"
                :selectedDates="selectedDates"
                :scheduleRange="scheduleRange"
                :todayYmd="todayYmd"
                @event-click="onEventClick"
                @date-click="onDateClick"
                @toggle-date="toggleSelectedDate"
                @date-range-change="loadEvents"
                @event-drop="onEventDrop"
            />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
:deep(.shift-1) { border-color: #14b8a6; background: #f0fdfa; }
:deep(.shift-2) { border-color: #3b82f6; background: #eff6ff; }
:deep(.shift-3) { border-color: #f97316; background: #fff7ed; }
</style>
