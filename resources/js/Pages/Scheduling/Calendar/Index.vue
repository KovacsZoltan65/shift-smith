<script setup>
import { Head } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

import CalendarBoard from "@/Pages/Scheduling/Calendar/Partials/CalendarBoard.vue";
import AssignmentCreateModal from "@/Pages/Scheduling/Calendar/AssignmentCreateModal.vue";
import AssignmentEditModal from "@/Pages/Scheduling/Calendar/AssignmentEditModal.vue";
import AssignmentBulkAssignModal from "@/Pages/Scheduling/Calendar/AssignmentBulkAssignModal.vue";
import AbsenceModal from "@/Pages/Scheduling/Calendar/AbsenceModal.vue";

import EmployeeService from "@/services/EmployeeService.js";
import WorkShiftService from "@/services/WorkShiftService.js";
import PositionService from "@/services/PositionService.js";
import WorkScheduleAssignmentService from "@/services/WorkScheduleAssignmentService.js";
import AbsenceService from "@/services/AbsenceService.js";
import MonthClosureService from "@/services/MonthClosureService.js";

const props = defineProps({
    title: { type: String, default: "" },
    current_company_id: { type: Number, required: true },
    schedules: { type: Array, default: () => [] },
    month_lock: {
        type: Object,
        default: () => ({
            year: null,
            month: null,
            is_closed: false,
            id: null,
            closed_at: null,
            closed_by_name: null,
            note: null,
        }),
    },
    permissions: {
        type: Object,
        default: () => ({
            viewer: false,
            planner: false,
            absenceViewer: false,
            absencePlanner: false,
            monthClosureViewAny: false,
            monthClosureClose: false,
            monthClosureReopen: false,
        }),
    },
});

// Kiinduló state a route query és a backendből kapott beosztások alapján
const fallbackTranslations = {
    "calendar.title": "Naptár",
    "calendar.fields.schedule": "Munkabeosztás",
    "calendar.fields.view": "Nézet",
    "calendar.fields.iso_week": "Hét száma (ISO)",
    "calendar.fields.year": "Év",
    "calendar.fields.month": "Hónap",
    "calendar.fields.date": "Dátum",
    "calendar.fields.employee_filter": "Dolgozó szűrő",
    "calendar.fields.shift_filter": "Műszak szűrő",
    "calendar.fields.position_filter": "Pozíció szűrő",
    "calendar.fields.range": "Intervallum",
    "calendar.fields.quick_select": "Gyors kijelölés",
    "calendar.fields.planner_mode": "Planner mód",
    "calendar.placeholders.schedule": "Munkabeosztás kiválasztása",
    "calendar.view_modes.week": "Heti",
    "calendar.view_modes.month": "Havi",
    "calendar.view_modes.day": "Napi",
    "calendar.schedule_status.published": "publikált",
    "calendar.schedule_status.draft": "draft",
    "calendar.months.january": "Január",
    "calendar.months.february": "Február",
    "calendar.months.march": "Március",
    "calendar.months.april": "Április",
    "calendar.months.may": "Május",
    "calendar.months.june": "Június",
    "calendar.months.july": "Július",
    "calendar.months.august": "Augusztus",
    "calendar.months.september": "Szeptember",
    "calendar.months.october": "Október",
    "calendar.months.november": "November",
    "calendar.months.december": "December",
    "calendar.quick_select.workdays": "H-P",
    "calendar.quick_select.weekends": "Szo-V",
    "calendar.quick_select.all": "Osszes",
    "calendar.quick_select.odd": "Páratlan",
    "calendar.quick_select.even": "Páros",
    "calendar.quick_select.current_week": "Aktuális hét",
    "calendar.quick_select.even_week": "Páros hét",
    "calendar.quick_select.odd_week": "Páratlan hét",
    "calendar.quick_select.today": "Mai nap",
    "calendar.quick_select.next_week": "Következő hét",
    "calendar.quick_select.month_remaining": "Hónap hátralévő",
    "calendar.quick_select.invert": "Invertálás",
    "calendar.actions.close_month": "Hónap lezárása",
    "calendar.actions.close": "Lezárás",
    "calendar.actions.reopen_month": "Hónap újranyitása",
    "calendar.actions.reopen": "Újranyitás",
    "calendar.actions.bulk_selected": "Bulk kijelöltek",
    "calendar.actions.mark_absence": "Távollét jelölése",
    "calendar.lock.closed_month": "Lezárt hónap",
    "calendar.lock.locked_month": "Hónap lezárva: :month",
    "calendar.lock.locked_at": "Hónap lezárva: :value",
    "calendar.lock.closed_by": "Lezárta: :name",
    "calendar.lock.note": "Megjegyzés: :note",
    "calendar.confirm.close_month": "Biztosan lezárod a(z) :month hónapot?",
    "calendar.confirm.reopen_month": "Biztosan újranyitod a(z) :month hónapot?",
    "calendar.messages.feed_error": "Naptár feed hiba.",
    "calendar.messages.details": "Részletek",
    "calendar.messages.assignment_created": "Hozzárendelés létrehozva.",
    "calendar.messages.assignment_updated": "Hozzárendelés frissítve.",
    "calendar.messages.assignment_deleted": "Hozzárendelés törölve.",
    "calendar.messages.save_failed": "Mentés sikertelen.",
    "calendar.messages.update_failed": "Frissítés sikertelen.",
    "calendar.messages.delete_failed": "Törlés sikertelen.",
    "calendar.messages.absence_created": "Távollétek létrehozva.",
    "calendar.messages.absence_updated": "Távollét frissítve.",
    "calendar.messages.absence_deleted": "Távollét törölve.",
    "calendar.messages.absence_save_failed": "Távollét mentése sikertelen.",
    "calendar.messages.absence_delete_failed": "Távollét törlése sikertelen.",
    "calendar.messages.invalid_data": "A megadott adatok hibásak.",
    "calendar.messages.validation": "Validáció",
    "calendar.messages.select_employee": "Válassz legalább egy dolgozót.",
    "calendar.messages.select_shift": "Válassz műszakot.",
    "calendar.messages.no_selected_day": "Nincs kijelölt nap.",
    "calendar.messages.bulk_saved": "Bulk mentés kész.",
    "calendar.messages.bulk_save_failed": "Bulk mentés sikertelen.",
    "calendar.messages.month_closed": "A(z) :month hónap lezárva.",
    "calendar.messages.close_month_failed": "A hónap lezárása sikertelen.",
    "calendar.messages.month_reopened": "A(z) :month hónap újranyitva.",
    "calendar.messages.planner_select_schedule": "Válassz beosztást a planner mód használatához.",
    "calendar.messages.planner_no_permission": "Nincs tervezési jogosultság.",
    "calendar.messages.month_closed_edit_disabled": "A(z) :month hónap lezárva, a szerkesztés nem engedélyezett.",
    "calendar.messages.published_schedule_not_editable": "A kiválasztott beosztás publikált, ezért nem szerkeszthető. Válassz draft státuszú beosztást.",
    "calendar.messages.past_period_not_editable": "Múltbeli időszak nem szerkeszthető.",
    "calendar.messages.days_selected": ":count nap kijelölve",
    "calendar.messages.quick_select_help": "Módok: kattintás = felülírás, ugyanaz újra = törlés, Ctrl/Cmd + kattintás = hozzáadás, Alt + kattintás = kivonás.",
    "common.error": "Hiba",
    "common.success": "Siker",
    "common.refresh": "Frissítés",
    "common.cancel": "Mégse",
    "common.clear": "Törlés",
    "validation.invalid": "Validációs hiba.",
    "month_closures.messages.reopen_failed": "Az újranyitás sikertelen.",
};

const formatFallback = (template, params = {}) =>
    Object.entries(params).reduce(
        (text, [key, value]) => text.replaceAll(`:${key}`, String(value ?? "")),
        template,
    );

const t = (key, params = {}) => {
    const translated = trans(key, params);
    if (translated !== key) return translated;
    return formatFallback(fallbackTranslations[key] ?? key, params);
};

const title = computed(() => props.title || t("calendar.title"));
const toast = useToast();
let confirm;
try {
    confirm = useConfirm();
} catch {
    confirm = { require: () => {} };
}
const $t = t;

const queryScheduleId = Number(
    new URLSearchParams(window.location.search).get("schedule_id") || 0,
);

const initialScheduleId = (() => {
    if (
        queryScheduleId > 0 &&
        props.schedules.some((x) => Number(x?.id) === queryScheduleId)
    ) {
        return queryScheduleId;
    }

    const preferred =
        props.schedules.find((x) => String(x?.status) !== "published") ??
        props.schedules?.[0];
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
const absenceOpen = ref(false);
const selectedAbsence = ref(null);
const absenceRange = ref({ from: null, to: null });
const createDate = ref(null);
const selectedEvent = ref(null);
const activeQuickSelect = ref(null);
const monthLock = ref(props.month_lock ?? {});
const closedMonthKeys = ref(
    props.month_lock?.is_closed &&
        props.month_lock?.year &&
        props.month_lock?.month
        ? [
              `${props.month_lock.year}-${String(props.month_lock.month).padStart(2, "0")}`,
          ]
        : [],
);

const employeeOptions = ref([]);
const shiftOptions = ref([]);
const positionOptions = ref([]);

// Jogosultságok és nézetből származó computed állapot
const canPlanner = computed(() => !!props.permissions?.planner);
const canAbsencePlanner = computed(() => !!props.permissions?.absencePlanner);
const canCloseMonth = computed(() => !!props.permissions?.monthClosureClose);
const canReopenMonth = computed(() => !!props.permissions?.monthClosureReopen);
const selectedSchedule = computed(
    () =>
        props.schedules.find(
            (x) => Number(x.id) === Number(scheduleId.value),
        ) ?? null,
);
const scheduleOptions = computed(() =>
    (props.schedules ?? []).map((row) => {
        const from = row?.date_from ? ` ${row.date_from}` : "";
        const to = row?.date_to ? ` - ${row.date_to}` : "";
        const status =
            row?.status === "published"
                ? ` [${t("calendar.schedule_status.published")}]`
                : ` [${t("calendar.schedule_status.draft")}]`;

        return {
            label: `${row?.name ?? t("calendar.fields.schedule")}${from}${to}${status}`,
            value: Number(row?.id ?? 0),
        };
    }),
);

const monthOptions = [
    { label: t("calendar.months.january"), value: 1 },
    { label: t("calendar.months.february"), value: 2 },
    { label: t("calendar.months.march"), value: 3 },
    { label: t("calendar.months.april"), value: 4 },
    { label: t("calendar.months.may"), value: 5 },
    { label: t("calendar.months.june"), value: 6 },
    { label: t("calendar.months.july"), value: 7 },
    { label: t("calendar.months.august"), value: 8 },
    { label: t("calendar.months.september"), value: 9 },
    { label: t("calendar.months.october"), value: 10 },
    { label: t("calendar.months.november"), value: 11 },
    { label: t("calendar.months.december"), value: 12 },
];

const viewModeOptions = [
    { label: t("calendar.view_modes.week"), value: "week" },
    { label: t("calendar.view_modes.month"), value: "month" },
    { label: t("calendar.view_modes.day"), value: "day" },
];

const weeklyQuickSelectOptions = [
    { label: t("calendar.quick_select.workdays"), value: "workdays" },
    { label: t("calendar.quick_select.weekends"), value: "weekends" },
    { label: t("calendar.quick_select.all"), value: "all" },
    { label: t("calendar.quick_select.odd"), value: "odd" },
    { label: t("calendar.quick_select.even"), value: "even" },
];

const monthlyQuickSelectOptions = [
    { label: t("calendar.quick_select.workdays"), value: "workdays" },
    { label: t("calendar.quick_select.weekends"), value: "weekends" },
    { label: t("calendar.quick_select.all"), value: "all" },
    { label: t("calendar.quick_select.odd"), value: "odd" },
    { label: t("calendar.quick_select.even"), value: "even" },
    { label: t("calendar.quick_select.current_week"), value: "current_week" },
    { label: t("calendar.quick_select.even_week"), value: "even_week" },
    { label: t("calendar.quick_select.odd_week"), value: "odd_week" },
    { label: t("calendar.quick_select.today"), value: "today" },
    { label: t("calendar.quick_select.next_week"), value: "next_week" },
    { label: t("calendar.quick_select.month_remaining"), value: "month_remaining" },
    { label: t("common.clear"), value: "clear" },
    { label: t("calendar.quick_select.invert"), value: "invert" },
];

const quickSelectOptions = computed(() => {
    if (viewMode.value === "month") return monthlyQuickSelectOptions;
    if (viewMode.value === "week") return weeklyQuickSelectOptions;
    return [];
});

// Dátum- és periódus segédfüggvények
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    return Array.from({ length: 11 }, (_, i) => {
        const value = currentYear - 5 + i;
        return { label: String(value), value };
    });
});

const getIsoWeek = (value) => {
    const date = new Date(
        Date.UTC(value.getFullYear(), value.getMonth(), value.getDate()),
    );
    const day = date.getUTCDay() || 7;
    date.setUTCDate(date.getUTCDate() + 4 - day);
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil(((date - yearStart) / 86400000 + 1) / 7);
};

const getIsoWeeksInYear = (isoYear) => getIsoWeek(new Date(isoYear, 11, 28));

const getIsoWeekStart = (isoYear, isoWeek) => {
    const jan4 = new Date(Date.UTC(isoYear, 0, 4));
    const jan4Day = jan4.getUTCDay() || 7;
    const monday = new Date(jan4);
    monday.setUTCDate(jan4.getUTCDate() - jan4Day + 1 + (isoWeek - 1) * 7);
    return new Date(
        monday.getUTCFullYear(),
        monday.getUTCMonth(),
        monday.getUTCDate(),
    );
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

const currentMonthKey = computed(() => todayYmd.value.slice(0, 7));

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

    const resolvedWeek = Math.min(
        Math.max(1, Number(weekNumber.value || 1)),
        Number(weekNumberMax.value || 52),
    );
    const start = getIsoWeekStart(Number(year.value), resolvedWeek);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, "0")}-${String(
        end.getDate(),
    ).padStart(2, "0")}`;
});

const selectedPeriodEditable = computed(() => {
    return (
        String(selectedDateYmd.value).slice(0, 7) >=
        String(currentMonthKey.value)
    );
});

const plannerModeEnabled = computed(() => {
    return (
        canPlanner.value &&
        plannerMode.value &&
        !!scheduleId.value &&
        selectedPeriodEditable.value &&
        selectedSchedule.value?.status !== "published" &&
        !isClosedMonthKey(viewedMonthKey.value)
    );
});

const plannerSwitchDisabled = computed(() => {
    return (
        !scheduleId.value ||
        !selectedPeriodEditable.value ||
        selectedSchedule.value?.status === "published" ||
        isClosedMonthKey(viewedMonthKey.value)
    );
});

const plannerDisabledReason = computed(() => {
    if (!scheduleId.value)
        return t("calendar.messages.planner_select_schedule");
    if (!canPlanner.value) return t("calendar.messages.planner_no_permission");
    if (isClosedMonthKey(viewedMonthKey.value)) {
        return t("calendar.messages.month_closed_edit_disabled", {
            month: viewedMonthKey.value,
        });
    }
    if (selectedSchedule.value?.status === "published") {
        return t("calendar.messages.published_schedule_not_editable");
    }
    if (!selectedPeriodEditable.value)
        return t("calendar.messages.past_period_not_editable");
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

    const resolvedWeek = Math.min(
        Math.max(1, Number(weekNumber.value || 1)),
        Number(weekNumberMax.value || 52),
    );
    return getIsoWeekStart(Number(year.value), resolvedWeek);
});

const currentRangeLabel = computed(() => {
    const start = scheduleRange.value.from;
    const end = scheduleRange.value.to;
    if (!start || !end) return "-";
    return `${start} - ${end}`;
});

const viewedMonthContext = computed(() => {
    if (viewMode.value === "month") {
        return {
            year: Number(year.value),
            month: Number(month.value),
        };
    }

    const date = viewMode.value === "day" ? dayDate.value : anchorDate.value;
    const dt = date instanceof Date ? date : new Date();

    return {
        year: dt.getFullYear(),
        month: dt.getMonth() + 1,
    };
});

const viewedMonthKey = computed(
    () =>
        `${viewedMonthContext.value.year}-${String(viewedMonthContext.value.month).padStart(2, "0")}`,
);

// A selectorok külön töltődnek, hogy a naptár feed újratöltése ne kérje le újra a statikus listákat.
const loadSelectors = async () => {
    const companyId = Number(props.current_company_id ?? 0);
    if (!companyId) return;

    const [employees, shifts, positions] = await Promise.all([
        EmployeeService.getToSelect({
            company_id: companyId,
            only_active: 1,
        }).catch(() => ({ data: [] })),
        WorkShiftService.getToSelect({
            company_id: companyId,
            only_active: 1,
        }).catch(() => ({ data: [] })),
        PositionService.getToSelect({
            company_id: companyId,
            only_active: 1,
        }).catch(() => ({ data: [] })),
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
    if (typeof value === "string" && /^\d{4}-\d{2}-\d{2}$/.test(value))
        return value;

    const dt = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(dt.getTime())) return "";

    return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, "0")}-${String(
        dt.getDate(),
    ).padStart(2, "0")}`;
};

const isClosedMonthKey = (value) =>
    closedMonthKeys.value.includes(String(value));

const isDateInClosedMonth = (value) => {
    const key = toDateKey(value);
    return key ? isClosedMonthKey(key.slice(0, 7)) : false;
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

    const utc = new Date(
        Date.UTC(dt.getFullYear(), dt.getMonth(), dt.getDate()),
    );
    const day = utc.getUTCDay() || 7;
    utc.setUTCDate(utc.getUTCDate() + 4 - day);

    const isoYear = utc.getUTCFullYear();
    const yearStart = new Date(Date.UTC(isoYear, 0, 1));
    const isoWeek = Math.ceil(((utc - yearStart) / 86400000 + 1) / 7);

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
    if (String(ymd).slice(0, 7) < String(currentMonthKey.value)) return false;
    if (scheduleRange.value.from && ymd < scheduleRange.value.from)
        return false;
    if (scheduleRange.value.to && ymd > scheduleRange.value.to) return false;
    if (isDateInClosedMonth(ymd)) return false;
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

        if (type === "workdays")
            return dayOfWeek !== null && dayOfWeek >= 1 && dayOfWeek <= 5;
        if (type === "weekends") return dayOfWeek === 0 || dayOfWeek === 6;
        if (type === "all" && viewMode.value === "week") {
            return dayOfWeek !== null && dayOfWeek >= 1 && dayOfWeek <= 5;
        }
        if (type === "all") return true;
        if (type === "odd") return dayOfMonth !== null && dayOfMonth % 2 === 1;
        if (type === "even") return dayOfMonth !== null && dayOfMonth % 2 === 0;
        if (type === "current_week") return isSameIsoWeek(day.date, today);
        if (type === "even_week") return isoWeek !== null && isoWeek % 2 === 0;
        if (type === "odd_week") return isoWeek !== null && isoWeek % 2 === 1;
        if (type === "today") return day.dateKey === todayYmd.value;
        if (type === "next_week")
            return isSameIsoWeek(day.date, todayPlusSeven);
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

// A gyors kijelölés támogatja a felülírás, hozzáadás és kivonás módot is a billentyűk alapján.
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
        selectedDates.value = selectedDates.value.filter(
            (key) => !targetSet.has(key),
        );
    } else if (additive) {
        selectedDates.value = Array.from(
            new Set([...selectedDates.value, ...targetKeys]),
        ).sort();
    } else {
        selectedDates.value = [...targetKeys].sort();
    }

    activeQuickSelect.value = type;
};

const syncSelectionWithVisibleRange = () => {
    if (viewMode.value !== "week" && viewMode.value !== "month") return;

    const visibleKeys = new Set(
        getVisibleGridDays()
            .map((date) => toDateKey(date))
            .filter(Boolean),
    );
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
        const resolvedWeek = Math.min(
            Math.max(1, Number(weekNumber.value || 1)),
            Number(weekNumberMax.value || 52),
        );
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
        monthLock.value = {
            year: viewedMonthContext.value.year,
            month: viewedMonthContext.value.month,
            is_closed: false,
            id: null,
            closed_at: null,
            closed_by_name: null,
            note: null,
        };
        closedMonthKeys.value = [];
        return;
    }

    loading.value = true;
    try {
        const { data } =
            await WorkScheduleAssignmentService.getCalendarFeed(
                buildFeedParams(),
            );
        events.value = Array.isArray(data?.data) ? data.data : [];
        feedMeta.value = {
            range: data?.meta?.range ?? { start: null, end: null },
            selected_date: data?.meta?.selected_date ?? null,
            editable: !!data?.meta?.editable,
        };
        monthLock.value = data?.meta?.month_lock ?? {
            year: viewedMonthContext.value.year,
            month: viewedMonthContext.value.month,
            is_closed: false,
            id: null,
            closed_at: null,
            closed_by_name: null,
            note: null,
        };
        closedMonthKeys.value = Array.isArray(data?.meta?.closed_month_keys)
            ? data.meta.closed_month_keys
            : [];

        if (
            !selectedPeriodEditable.value ||
            selectedSchedule.value?.status === "published" ||
            viewedMonthLock.value?.is_closed
        ) {
            plannerMode.value = false;
        }
    } catch (e) {
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail: e?.response?.data?.message || t("calendar.messages.feed_error"),
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const refresh = async () => {
    clearSelectedDatesState();
    await loadEvents();
};

const clearSelectedDatesState = () => {
    selectedDates.value = [];
    activeQuickSelect.value = null;
};

const viewedMonthLock = computed(() => {
    if (
        Number(monthLock.value?.year ?? 0) ===
            Number(viewedMonthContext.value.year) &&
        Number(monthLock.value?.month ?? 0) ===
            Number(viewedMonthContext.value.month)
    ) {
        return monthLock.value;
    }

    return {
        year: viewedMonthContext.value.year,
        month: viewedMonthContext.value.month,
        is_closed: isClosedMonthKey(viewedMonthKey.value),
        id: null,
        closed_at: null,
        closed_by_name: null,
        note: null,
    };
});

const monthLockTooltip = computed(() => {
    if (!viewedMonthLock.value?.is_closed) {
        return "";
    }

    const parts = [];

    if (viewedMonthLock.value?.closed_at) {
        parts.push(
            t("calendar.lock.locked_at", {
                value: viewedMonthLock.value.closed_at,
            }),
        );
    } else {
        parts.push(t("calendar.lock.locked_month", { month: viewedMonthKey.value }));
    }

    if (viewedMonthLock.value?.closed_by_name) {
        parts.push(
            t("calendar.lock.closed_by", {
                name: viewedMonthLock.value.closed_by_name,
            }),
        );
    }

    if (viewedMonthLock.value?.note) {
        parts.push(t("calendar.lock.note", { note: viewedMonthLock.value.note }));
    }

    return parts.join(" | ");
});

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
    if (String(date).slice(0, 7) < String(currentMonthKey.value)) return;
    if (isDateInClosedMonth(date)) return;

    createDate.value = date;
    createOpen.value = true;
};

const onEventClick = (event) => {
    if (event?.extendedProps?.entity_type === "absence") {
        selectedAbsence.value = event;
        if (canAbsencePlanner.value) {
            absenceOpen.value = true;
            return;
        }
    }

    selectedEvent.value = event;
    if (
        plannerModeEnabled.value &&
        !!event?.editable &&
        !isDateInClosedMonth(event?.start)
    ) {
        editOpen.value = true;
        return;
    }

    toast.add({
        severity: "info",
        summary: t("calendar.messages.details"),
        detail: event.title,
        life: 3000,
    });
};

const handleCreate = async (payload) => {
    try {
        await WorkScheduleAssignmentService.createAssignment(payload);
        createOpen.value = false;
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail: t("calendar.messages.assignment_created"),
            life: 2200,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail: e?.response?.data?.message || t("calendar.messages.save_failed"),
            life: 3500,
        });
    }
};

const handleUpdate = async ({ id, payload }) => {
    try {
        await WorkScheduleAssignmentService.updateAssignment(id, payload);
        editOpen.value = false;
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail: t("calendar.messages.assignment_updated"),
            life: 2200,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail: e?.response?.data?.message || t("calendar.messages.update_failed"),
            life: 3500,
        });
    }
};

const handleDelete = async (id) => {
    try {
        await WorkScheduleAssignmentService.deleteAssignment(id);
        editOpen.value = false;
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail: t("calendar.messages.assignment_deleted"),
            life: 2200,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail: e?.response?.data?.message || t("calendar.messages.delete_failed"),
            life: 3500,
        });
    }
};

const openAbsenceCreate = () => {
    selectedAbsence.value = null;
    absenceRange.value = {
        from: selectedDateYmd.value,
        to: selectedDateYmd.value,
    };
    absenceOpen.value = true;
};

const handleAbsenceSubmit = async ({ id, payload }) => {
    try {
        if (id > 0) {
            await AbsenceService.update(id, payload);
        } else {
            await AbsenceService.store(payload);
        }

        absenceOpen.value = false;
        selectedAbsence.value = null;
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail:
                id > 0
                    ? t("calendar.messages.absence_updated")
                    : t("calendar.messages.absence_created"),
            life: 2200,
        });
    } catch (e) {
        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === "object") {
            const detail = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" | ");
            toast.add({
                severity: "warn",
                summary: t("validation.invalid"),
                detail: detail || t("calendar.messages.invalid_data"),
                life: 5000,
            });
            return;
        }
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail:
                e?.response?.data?.message || t("calendar.messages.absence_save_failed"),
            life: 3500,
        });
    }
};

const handleAbsenceDelete = async (id) => {
    try {
        await AbsenceService.destroy(id);
        absenceOpen.value = false;
        selectedAbsence.value = null;
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail: t("calendar.messages.absence_deleted"),
            life: 2200,
        });
    } catch (e) {
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail:
                e?.response?.data?.message || t("calendar.messages.absence_delete_failed"),
            life: 3500,
        });
    }
};

const onEventDrop = async ({ id, date }) => {
    if (isDateInClosedMonth(date)) return;

    const row = events.value.find((x) => Number(x.id) === Number(id));
    if (
        !row ||
        !row.editable ||
        row?.extendedProps?.entity_type !== "shift_assignment"
    )
        return;

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
    if (isDateInClosedMonth(ymd)) return;

    if (selectedDates.value.includes(ymd)) {
        selectedDates.value = selectedDates.value.filter((x) => x !== ymd);
    } else {
        selectedDates.value = [...selectedDates.value, ymd].sort();
    }

    activeQuickSelect.value = null;
};

// CRUD műveletek és naptár feed lifecycle
const handleBulk = async (payload) => {
    if (viewedMonthLock.value?.is_closed) {
        toast.add({
            severity: "warn",
            summary: t("calendar.lock.closed_month"),
            detail: monthLockTooltip.value,
            life: 4000,
        });
        return;
    }

    if (!payload.employee_ids?.length) {
        toast.add({
            severity: "warn",
            summary: t("calendar.messages.validation"),
            detail: t("calendar.messages.select_employee"),
            life: 3200,
        });
        return;
    }
    if (!payload.work_shift_id) {
        toast.add({
            severity: "warn",
            summary: t("calendar.messages.validation"),
            detail: t("calendar.messages.select_shift"),
            life: 3200,
        });
        return;
    }
    if (!payload.dates?.length) {
        toast.add({
            severity: "warn",
            summary: t("calendar.messages.validation"),
            detail: t("calendar.messages.no_selected_day"),
            life: 3200,
        });
        return;
    }

    try {
        await WorkScheduleAssignmentService.bulkUpsert(payload);
        bulkOpen.value = false;
        clearSelectedDatesState();
        await loadEvents();
        toast.add({
            severity: "success",
            summary: t("common.success"),
            detail: t("calendar.messages.bulk_saved"),
            life: 2200,
        });
    } catch (e) {
        const errors = e?.response?.data?.errors;
        if (errors && typeof errors === "object") {
            const detail = Object.values(errors)
                .flat()
                .filter(Boolean)
                .join(" | ");
            toast.add({
                severity: "warn",
                summary: t("validation.invalid"),
                detail: detail || t("calendar.messages.invalid_data"),
                life: 5000,
            });
            return;
        }
        toast.add({
            severity: "error",
            summary: t("common.error"),
            detail: e?.response?.data?.message || t("calendar.messages.bulk_save_failed"),
            life: 3500,
        });
    }
};

const handleCloseMonth = () => {
    if (!canCloseMonth.value) return;

    confirm.require({
        message: t("calendar.confirm.close_month", { month: viewedMonthKey.value }),
        header: t("calendar.actions.close_month"),
        acceptLabel: t("calendar.actions.close"),
        rejectLabel: t("common.cancel"),
        accept: async () => {
            try {
                await MonthClosureService.close({
                    year: viewedMonthContext.value.year,
                    month: viewedMonthContext.value.month,
                });
                clearSelectedDatesState();
                await loadEvents();
                toast.add({
                    severity: "success",
                    summary: t("common.success"),
                    detail: t("calendar.messages.month_closed", { month: viewedMonthKey.value }),
                    life: 2500,
                });
            } catch (e) {
                toast.add({
                    severity: "error",
                    summary: t("common.error"),
                    detail:
                        e?.response?.data?.message ||
                        t("calendar.messages.close_month_failed"),
                    life: 4000,
                });
            }
        },
    });
};

const handleReopenMonth = () => {
    if (!canReopenMonth.value || !viewedMonthLock.value?.id) return;

    confirm.require({
        message: t("calendar.confirm.reopen_month", { month: viewedMonthKey.value }),
        header: t("calendar.actions.reopen_month"),
        acceptLabel: t("calendar.actions.reopen"),
        rejectLabel: t("common.cancel"),
        accept: async () => {
            try {
                await MonthClosureService.reopen(
                    Number(viewedMonthLock.value.id),
                );
                await loadEvents();
                toast.add({
                    severity: "success",
                    summary: t("common.success"),
                    detail: t("calendar.messages.month_reopened", { month: viewedMonthKey.value }),
                    life: 2500,
                });
            } catch (e) {
                toast.add({
                    severity: "error",
                    summary: t("common.error"),
                    detail:
                        e?.response?.data?.message ||
                        t("month_closures.messages.reopen_failed"),
                    life: 4000,
                });
            }
        },
    });
};

watch(
    viewMode,
    async () => {
        resetViewFilters();
        clearSelectedDatesState();
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

watch(
    plannerSwitchDisabled,
    (disabled) => {
        if (disabled) {
            plannerMode.value = false;
        }
    },
    { immediate: true },
);

watch(
    viewedMonthLock,
    (lock) => {
        if (!lock?.is_closed) return;

        // Lezárt hónapnál minden szerkesztő állapotot azonnal vissza kell zárni, hogy a UI ne maradjon félrevezető.
        clearSelectedDatesState();
        createOpen.value = false;
        editOpen.value = false;
        bulkOpen.value = false;
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
    <ConfirmDialog />

    <AssignmentCreateModal
        v-model="createOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :defaultDate="createDate"
        :loading="loading"
        :disabled="viewedMonthLock?.is_closed"
        @submit="handleCreate"
    />

    <AssignmentEditModal
        v-model="editOpen"
        :assignment="selectedEvent"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :loading="loading"
        :disabled="viewedMonthLock?.is_closed"
        @update="handleUpdate"
        @delete="handleDelete"
    />

    <AssignmentBulkAssignModal
        v-model="bulkOpen"
        :companyId="current_company_id"
        :scheduleId="Number(scheduleId || 0)"
        :selectedDates="selectedDates"
        :employeeOptions="employeeOptions"
        :loading="loading"
        :disabled="viewedMonthLock?.is_closed"
        @submit="handleBulk"
    />

    <AbsenceModal
        v-model="absenceOpen"
        :absence="selectedAbsence"
        :companyId="current_company_id"
        :employeeOptions="employeeOptions"
        :defaultRange="absenceRange"
        :loading="loading"
        @submit="handleAbsenceSubmit"
        @delete="handleAbsenceDelete"
    />

    <AuthenticatedLayout>
        <div class="space-y-4 p-6">
            <div
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4"
            >
                <div class="min-w-80">
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.schedule") }}</label
                    >
                    <Select
                        v-model="scheduleId"
                        :options="scheduleOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                        :placeholder="$t('calendar.placeholders.schedule')"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.view") }}</label
                    >
                    <SelectButton
                        v-model="viewMode"
                        :options="viewModeOptions"
                        optionLabel="label"
                        optionValue="value"
                    />
                </div>

                <div v-if="viewMode === 'week'" class="min-w-40">
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.iso_week") }}</label
                    >
                    <InputNumber
                        v-model="weekNumber"
                        :min="1"
                        :max="weekNumberMax"
                        showButtons
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'week'" class="min-w-32">
                    <label class="mb-1 block text-xs text-slate-600">{{ $t("calendar.fields.year") }}</label>
                    <Select
                        v-model="year"
                        :options="yearOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'month'" class="min-w-52">
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.month") }}</label
                    >
                    <Select
                        v-model="month"
                        :options="monthOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'month'" class="min-w-32">
                    <label class="mb-1 block text-xs text-slate-600">{{ $t("calendar.fields.year") }}</label>
                    <Select
                        v-model="year"
                        :options="yearOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full"
                    />
                </div>

                <div v-if="viewMode === 'day'" class="min-w-56">
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.date") }}</label
                    >
                    <DatePicker
                        v-model="dayDate"
                        dateFormat="yy-mm-dd"
                        showIcon
                        class="w-full"
                    />
                </div>

                <div class="min-w-64">
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.employee_filter") }}</label
                    >
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
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.shift_filter") }}</label
                    >
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
                    <label class="mb-1 block text-xs text-slate-600"
                        >{{ $t("calendar.fields.position_filter") }}</label
                    >
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

                <Button
                    icon="pi pi-refresh"
                    severity="secondary"
                    :loading="loading"
                    @click="refresh"
                />

                <div class="text-xs text-slate-600">
                    {{ $t("calendar.fields.range") }}: <b>{{ currentRangeLabel }}</b>
                </div>

                <div
                    v-if="viewedMonthLock?.is_closed"
                    v-tooltip.bottom="monthLockTooltip"
                    class="inline-flex items-center gap-2 rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800"
                >
                    <i class="pi pi-lock" />
                    <span>{{ $t("calendar.lock.locked_month", { month: viewedMonthKey }) }}</span>
                </div>

                <Button
                    v-if="!viewedMonthLock?.is_closed && canCloseMonth"
                    :label="$t('calendar.actions.close_month')"
                    icon="pi pi-lock"
                    severity="warning"
                    @click="handleCloseMonth"
                />

                <Button
                    v-if="
                        viewedMonthLock?.is_closed &&
                        canReopenMonth &&
                        viewedMonthLock?.id
                    "
                    :label="$t('calendar.actions.reopen')"
                    icon="pi pi-lock-open"
                    severity="secondary"
                    @click="handleReopenMonth"
                />

                <div
                    v-if="
                        plannerModeEnabled &&
                        (viewMode === 'week' || viewMode === 'month')
                    "
                    class="flex flex-col gap-1"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-600"
                            >{{ $t("calendar.fields.quick_select") }}:</span
                        >
                        <Button
                            v-for="opt in quickSelectOptions"
                            :key="opt.value"
                            :label="opt.label"
                            size="small"
                            :severity="
                                activeQuickSelect === opt.value
                                    ? 'primary'
                                    : 'secondary'
                            "
                            :outlined="activeQuickSelect !== opt.value"
                            :disabled="viewedMonthLock?.is_closed"
                            @click="applyQuickSelect(opt.value, $event)"
                        />
                        <span class="text-xs text-slate-600"
                            >{{ $t("calendar.messages.days_selected", { count: selectedDates.length }) }}</span
                        >
                    </div>
                    <div class="text-[11px] text-slate-500">
                        {{ $t("calendar.messages.quick_select_help") }}
                    </div>
                </div>

                <div v-if="canPlanner" class="ml-auto flex items-center gap-2">
                    <span class="text-sm">{{ $t("calendar.fields.planner_mode") }}</span>
                    <ToggleSwitch
                        v-model="plannerMode"
                        :disabled="plannerSwitchDisabled"
                    />
                </div>

                <div
                    v-if="canPlanner && plannerDisabledReason"
                    class="text-xs text-amber-700"
                >
                    {{ plannerDisabledReason }}
                </div>

                <Button
                    v-if="plannerModeEnabled"
                    :label="$t('calendar.actions.bulk_selected')"
                    icon="pi pi-list-check"
                    :disabled="
                        selectedDates.length === 0 || viewedMonthLock?.is_closed
                    "
                    @click="bulkOpen = true"
                />

                <Button
                    v-if="canAbsencePlanner"
                    :label="$t('calendar.actions.mark_absence')"
                    icon="pi pi-briefcase"
                    severity="contrast"
                    @click="openAbsenceCreate"
                />
            </div>

            <CalendarBoard
                :events="events"
                :viewMode="viewMode"
                :anchorDate="anchorDate"
                :plannerMode="plannerModeEnabled"
                :selectedDates="selectedDates"
                :closedMonthKeys="closedMonthKeys"
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
:deep(.shift-1) {
    border-color: #14b8a6;
    background: #f0fdfa;
}
:deep(.shift-2) {
    border-color: #3b82f6;
    background: #eff6ff;
}
:deep(.shift-3) {
    border-color: #f97316;
    background: #fff7ed;
}
</style>
