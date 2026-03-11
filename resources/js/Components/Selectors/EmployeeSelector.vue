<script setup>
import { computed, onMounted, ref, watch } from "vue";
import Service from "@/services/EmployeeService.js";
import { csrfFetch } from "@/lib/csrfFetch";

/**
 * EmployeeSelector
 *
 * Dolgozóválasztó komponens normál select és szerveroldali kereső módban.
 * Hierarchia- és scheduling-folyamatokban is használható, ezért támogatja
 * a company scope-ot, az objektum-visszaadást és az elemek kizárását is.
 */
const props = defineProps({
    modelValue: { type: [String, Number, Object, null], default: null },
    companyId: { type: [String, Number, null], default: null },
    onlyActive: { type: Boolean, default: true },
    filter: { type: Boolean, default: null },
    placeholder: { type: String, default: "" },
    inputId: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    serverSearch: { type: Boolean, default: false },
    returnObject: { type: Boolean, default: false },
    searchRouteName: { type: String, default: "org.hierarchy.employees.search" },
    searchLimit: { type: Number, default: 20 },
    minQueryLength: { type: Number, default: 1 },
    excludeEmployeeIds: { type: Array, default: () => [] },
});

const emit = defineEmits(["update:modelValue", "selected"]);

const employees = ref([]);
const suggestions = ref([]);
const selectedSuggestion = ref(null);
const isLoading = ref(false);

const model = computed({
    get: () => props.modelValue,
    set: (val) => emit("update:modelValue", val),
});

// A placeholder módváltástól függ: sima selector vagy szerveroldali kereső használata.
const shouldUseFilter = computed(() => {
    if (props.filter === true) return true;
    if (props.filter === false) return false;
    return employees.value.length > 10;
});

const placeholderText = computed(() => {
    if (props.placeholder) {
        return props.placeholder;
    }

    return props.serverSearch ? "Dolgozó keresése..." : "Dolgozó kiválasztása";
});

// A kizárások Set formában olcsóbbak a gyakori szűrési ellenőrzésekhez.
const excludedIds = computed(() =>
    new Set(
        (props.excludeEmployeeIds ?? [])
            .map((id) => Number(id))
            .filter((id) => Number.isFinite(id) && id > 0),
    ),
);

const filterExcluded = (rows) =>
    (Array.isArray(rows) ? rows : []).filter((row) => !excludedIds.value.has(Number(row?.id)));

// A modell lehet id vagy teljes objektum is, ezért az opciók közül mindig explicit feloldjuk.
const resolveSelectedById = (id) => {
    if (id === null || id === undefined || id === "") {
        return null;
    }

    const normalized = Number(id);
    if (!Number.isFinite(normalized)) {
        return null;
    }

    return (
        suggestions.value.find((row) => Number(row.id) === normalized) ??
        employees.value.find((row) => Number(row.id) === normalized) ??
        null
    );
};

const emitSelected = (row) => {
    if (row === null) {
        emit("update:modelValue", null);
        emit("selected", null);
        return;
    }

    emit("selected", row);
    emit("update:modelValue", props.returnObject ? row : Number(row.id));
};

const loadEmployees = async () => {
    if (props.serverSearch) {
        return;
    }

    isLoading.value = true;
    try {
        const baseParams = {};

        if (props.companyId !== null && props.companyId !== "" && props.companyId !== undefined) {
            baseParams.company_id = Number(props.companyId);
        }

        const { data } = await Service.getToSelect({
            ...baseParams,
            only_active: props.onlyActive ? 1 : 0,
        });

        const list = Array.isArray(data) ? data : [];

        if (props.onlyActive && list.length === 0) {
            const fallback = await Service.getToSelect({
                ...baseParams,
                only_active: 0,
            });
            employees.value = filterExcluded(Array.isArray(fallback?.data) ? fallback.data : []);
            return;
        }

        employees.value = filterExcluded(list);
    } catch {
        employees.value = [];
    } finally {
        isLoading.value = false;
    }
};

const searchEmployees = async (event) => {
    if (!props.serverSearch) {
        return;
    }

    const query = String(event?.query ?? "").trim();
    if (query.length < props.minQueryLength) {
        suggestions.value = [];
        return;
    }

    if (!props.companyId) {
        suggestions.value = [];
        return;
    }

    isLoading.value = true;
    try {
        const params = new URLSearchParams({
            company_id: String(Number(props.companyId)),
            q: query,
            limit: String(Math.max(1, Math.min(50, Number(props.searchLimit) || 20))),
        });

        const response = await csrfFetch(`${route(props.searchRouteName)}?${params.toString()}`, {
            method: "GET",
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || "Dolgozó keresése sikertelen.");
        }

        suggestions.value = filterExcluded(Array.isArray(payload?.data) ? payload.data : []);
    } catch {
        suggestions.value = [];
    } finally {
        isLoading.value = false;
    }
};

const onSuggestionSelect = (event) => {
    const selected = event?.value ?? selectedSuggestion.value;
    emitSelected(selected ?? null);
};

const onSuggestionClear = () => {
    selectedSuggestion.value = null;
    emitSelected(null);
};

const onSelectChange = (event) => {
    if (props.serverSearch) {
        return;
    }

    const selectedId = event?.value ?? model.value ?? null;
    const selected = employees.value.find((row) => Number(row.id) === Number(selectedId)) ?? null;
    emit("selected", selected);
};

const onSelectClear = () => {
    if (props.serverSearch) {
        return;
    }

    emit("selected", null);
};

onMounted(() => {
    loadEmployees();
});

watch(
    () => [props.companyId, props.onlyActive, props.serverSearch],
    () => {
        if (props.serverSearch) {
            suggestions.value = [];
            selectedSuggestion.value = null;
            emitSelected(null);
            return;
        }

        loadEmployees();
    },
);

watch(
    () => props.modelValue,
    (value) => {
        if (!props.serverSearch) {
            return;
        }

        if (props.returnObject) {
            selectedSuggestion.value = value ?? null;
            return;
        }

        selectedSuggestion.value = resolveSelectedById(value);
    },
    { immediate: true },
);
</script>

<template>
    <AutoComplete
        v-if="serverSearch"
        v-model="selectedSuggestion"
        :suggestions="suggestions"
        optionLabel="full_name"
        :dropdown="false"
        :placeholder="placeholderText"
        :inputId="inputId"
        :disabled="disabled || !companyId"
        :minLength="minQueryLength"
        class="w-full"
        inputClass="w-full"
        forceSelection
        @complete="searchEmployees"
        @item-select="onSuggestionSelect"
        @clear="onSuggestionClear"
    >
        <template #option="{ option }">
            <div class="flex min-w-0 flex-col">
                <span class="truncate font-medium text-slate-800">{{ option.full_name }}</span>
                <span class="truncate text-xs text-slate-500">
                    {{ option.position || "-" }}<span v-if="option.email"> • {{ option.email }}</span>
                </span>
            </div>
        </template>
    </AutoComplete>

    <Select
        v-else
        v-model="model"
        :options="employees"
        optionLabel="name"
        optionValue="id"
        :placeholder="placeholderText"
        class="w-full"
        :loading="isLoading"
        :filter="shouldUseFilter"
        showClear
        :inputId="inputId"
        :disabled="disabled"
        @change="onSelectChange"
        @clear="onSelectClear"
    />
</template>
