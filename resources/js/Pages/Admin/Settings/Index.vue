<script setup>
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Button from "primevue/button";
import Checkbox from "primevue/checkbox";
import InputText from "primevue/inputtext";
import InputNumber from "primevue/inputnumber";
import Select from "primevue/select";
import MultiSelect from "primevue/multiselect";
import SelectButton from "primevue/selectbutton";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    title: { type: String, default: "Beállítások" },
    initialLevel: { type: String, default: "app" },
    current_company_id: { type: [Number, null], default: null },
});

const toast = useToast();

const normalizeLevel = (value) => (["app", "company", "user"].includes(value) ? value : "app");
const level = ref(normalizeLevel(props.initialLevel));
const levelOptions = [
    { label: "App", value: "app" },
    { label: "Company", value: "company" },
    { label: "User", value: "user" },
];

const loading = ref(false);
const saving = ref(false);
const groups = ref([]);
const search = ref("");
const changedOnly = ref(false);
const errors = reactive({});

const companyId = ref(props.current_company_id ?? null);
const userId = ref(null);
const userQuery = ref("");
const userOptions = ref([]);
let searchTimer = null;

const showCompanySelector = computed(() => level.value === "company" || level.value === "user");
const showUserSelector = computed(() => level.value === "user");

const sourceBadge = (source) => {
    if (source === "user") return { text: "User override", class: "bg-cyan-100 text-cyan-800" };
    if (source === "company") return { text: "Company override", class: "bg-amber-100 text-amber-800" };
    if (source === "app") return { text: "App override", class: "bg-indigo-100 text-indigo-800" };
    return { text: "Default", class: "bg-slate-100 text-slate-700" };
};

const deepCopy = (value) => (value === undefined ? undefined : JSON.parse(JSON.stringify(value)));
const isEqual = (a, b) => JSON.stringify(a) === JSON.stringify(b);

const normalizeErrorBag = (bag) => {
    Object.keys(errors).forEach((k) => delete errors[k]);
    Object.entries(bag ?? {}).forEach(([k, v]) => {
        errors[k] = Array.isArray(v) ? v[0] : String(v);
    });
};

const parseRows = (payload) =>
    (payload ?? []).map((group) => ({
        ...group,
        open: true,
        items: (group.items ?? []).map((item) => ({
            ...item,
            edit_value: deepCopy(item.effective_value),
            original_value: deepCopy(item.effective_value),
        })),
    }));

const buildQuery = () => {
    const resolvedLevel = normalizeLevel(level.value);
    const q = {
        level: resolvedLevel,
        company_id: showCompanySelector.value ? companyId.value ?? "" : "",
        user_id: showUserSelector.value ? userId.value ?? "" : "",
        search: search.value?.trim() || "",
        changed_only: changedOnly.value ? 1 : 0,
    };
    Object.keys(q).forEach((k) => {
        if (q[k] === "" || q[k] === null || q[k] === undefined) delete q[k];
    });
    return new URLSearchParams(q).toString();
};

const fetchSettings = async () => {
    if (showCompanySelector.value && !companyId.value) {
        groups.value = [];
        return;
    }
    if (showUserSelector.value && !userId.value) {
        groups.value = [];
        return;
    }

    loading.value = true;
    try {
        const res = await fetch(`/settings/fetch?${buildQuery()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body?.message || `HTTP ${res.status}`);
        }

        const body = await res.json();
        groups.value = parseRows(body?.data ?? []);
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "A beállítások lekérése sikertelen.",
            life: 3500,
        });
    } finally {
        loading.value = false;
    }
};

const parentValueForLevel = (item) => {
    if (level.value === "app") return deepCopy(item.default_value);
    if (level.value === "company") return deepCopy(item.app_value ?? item.default_value);
    return deepCopy(item.company_value ?? item.app_value ?? item.default_value);
};

const resetAtLevel = (item) => {
    item.edit_value = parentValueForLevel(item);
};

const resetGroup = (group) => {
    (group.items ?? []).forEach((item) => {
        item.edit_value = deepCopy(item.original_value);
    });
};

const resetAll = () => {
    groups.value.forEach((group) => resetGroup(group));
};

const collectChanged = () => {
    const changed = [];
    groups.value.forEach((group) => {
        group.items.forEach((item) => {
            if (!isEqual(item.edit_value, item.original_value)) {
                changed.push({ key: item.key, value: item.edit_value });
            }
        });
    });
    return changed;
};

const save = async () => {
    const values = collectChanged();
    if (!values.length) {
        toast.add({ severity: "info", summary: "Info", detail: "Nincs mentendő változás.", life: 2000 });
        return;
    }

    saving.value = true;
    normalizeErrorBag({});

    try {
        const res = await csrfFetch("/settings/save", {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({
                level: normalizeLevel(level.value),
                company_id: showCompanySelector.value ? companyId.value : null,
                user_id: showUserSelector.value ? userId.value : null,
                values,
            }),
        });

        if (res.status === 422) {
            const body = await res.json().catch(() => ({}));
            normalizeErrorBag(body?.errors ?? {});
            throw new Error(body?.message || "Validációs hiba.");
        }

        if (!res.ok) {
            const body = await res.json().catch(() => ({}));
            throw new Error(body?.message || `HTTP ${res.status}`);
        }

        const body = await res.json();
        toast.add({
            severity: "success",
            summary: "Siker",
            detail: body?.message || "Beállítások mentve.",
            life: 2500,
        });

        await fetchSettings();
    } catch (e) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: e?.message || "Mentés sikertelen.",
            life: 3500,
        });
    } finally {
        saving.value = false;
    }
};

const fieldError = (key) => errors[`values.${key}`] ?? null;

const mapUserRows = (json) => {
    const rows = Array.isArray(json?.data?.data)
        ? json.data.data
        : Array.isArray(json?.data)
          ? json.data
          : [];

    return rows.map((r) => ({
        id: Number(r.id),
        label: r.name ?? (`${r.first_name ?? ""} ${r.last_name ?? ""}`.trim() || `#${r.id}`),
    }));
};

const fetchUsers = async (term = "") => {
    try {
        const q = new URLSearchParams({
            page: "1",
            per_page: "20",
            field: "name",
            order: "asc",
            search: term,
        });
        const res = await fetch(`/users/fetch?${q.toString()}`, {
            headers: { "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
        });
        if (!res.ok) return;
        const json = await res.json();
        userOptions.value = mapUserRows(json);
    } catch (_) {}
};

watch(level, async () => {
    level.value = normalizeLevel(level.value);

    if (level.value !== "user") {
        userId.value = null;
        userOptions.value = [];
    }
    normalizeErrorBag({});
    await fetchSettings();
});

watch([companyId, userId, changedOnly], () => fetchSettings());

watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchSettings(), 300);
});

watch(userQuery, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchUsers(value?.trim() || ""), 250);
});

onMounted(async () => {
    await fetchUsers();
    await fetchSettings();
});
</script>

<template>
    <Head :title="title" />

    <Toast />

    <AuthenticatedLayout>
        <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ title }}</h1>

                <SelectButton v-model="level" :options="levelOptions" optionLabel="label" optionValue="value" />

                <div v-if="showCompanySelector" class="min-w-[260px]">
                    <CompanySelector v-model="companyId" placeholder="Cég kiválasztása..." />
                </div>

                <div v-if="showUserSelector" class="min-w-[260px]">
                    <Select
                        v-model="userId"
                        :options="userOptions"
                        optionLabel="label"
                        optionValue="id"
                        filter
                        v-model:filterValue="userQuery"
                        placeholder="Felhasználó kiválasztása..."
                        class="w-full"
                    />
                </div>

                <span class="p-input-icon-left">
                    <i class="pi pi-search" />
                    <InputText v-model="search" placeholder="Keresés kulcs/név szerint..." />
                </span>

                <div class="flex items-center gap-2">
                    <Checkbox v-model="changedOnly" binary inputId="changedOnly" />
                    <label for="changedOnly" class="text-sm text-slate-700">Csak módosított</label>
                </div>

                <Button label="Reset all" severity="secondary" :disabled="saving || loading" @click="resetAll" />
                <Button label="Mentés" icon="pi pi-save" :loading="saving" :disabled="loading || saving" @click="save" />
            </div>

            <div v-if="!groups.length && !loading" class="rounded border border-slate-200 bg-white p-4 text-slate-600">
                Nincs megjeleníthető beállítás a kiválasztott szűrőkkel.
            </div>

            <div v-for="group in groups" :key="group.group" class="rounded border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <button class="text-left font-semibold text-slate-800" @click="group.open = !group.open">
                        {{ group.group }}
                    </button>
                    <div class="flex items-center gap-2">
                        <Button label="Reset group" text size="small" @click="resetGroup(group)" />
                        <i class="pi" :class="group.open ? 'pi-chevron-up' : 'pi-chevron-down'" />
                    </div>
                </div>

                <div v-if="group.open" class="divide-y divide-slate-100">
                    <div
                        v-for="item in group.items"
                        :key="item.key"
                        class="p-4"
                        :class="{ 'opacity-70': item.inherited, 'bg-slate-50': item.overridden_at_level }"
                    >
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <div class="font-medium text-slate-900">{{ item.label }}</div>
                            <code class="rounded bg-slate-100 px-2 py-0.5 text-xs">{{ item.key }}</code>
                            <span
                                class="rounded px-2 py-0.5 text-xs font-medium"
                                :class="sourceBadge(item.source).class"
                            >
                                {{ sourceBadge(item.source).text }}
                            </span>
                            <span v-if="item.overridden_at_level" class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">
                                Override ezen a szinten
                            </span>
                            <Button
                                label="Visszaállítás erre a szintre"
                                size="small"
                                text
                                :disabled="saving"
                                @click="resetAtLevel(item)"
                            />
                        </div>

                        <div v-if="item.description" class="mb-2 text-sm text-slate-600">{{ item.description }}</div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs text-slate-600">Effective value</label>

                                <Checkbox
                                    v-if="item.type === 'bool'"
                                    v-model="item.edit_value"
                                    binary
                                    :disabled="saving"
                                />

                                <InputNumber
                                    v-else-if="item.type === 'int'"
                                    v-model="item.edit_value"
                                    class="w-full"
                                    :useGrouping="false"
                                    :disabled="saving"
                                />

                                <InputNumber
                                    v-else-if="item.type === 'float'"
                                    v-model="item.edit_value"
                                    class="w-full"
                                    :useGrouping="false"
                                    :minFractionDigits="0"
                                    :maxFractionDigits="4"
                                    :disabled="saving"
                                />

                                <Select
                                    v-else-if="item.type === 'select'"
                                    v-model="item.edit_value"
                                    :options="item.options ?? []"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                    :disabled="saving"
                                />

                                <MultiSelect
                                    v-else-if="item.type === 'multiselect'"
                                    v-model="item.edit_value"
                                    :options="item.options ?? []"
                                    optionLabel="label"
                                    optionValue="value"
                                    class="w-full"
                                    :disabled="saving"
                                />

                                <InputText
                                    v-else-if="item.type === 'json'"
                                    class="w-full font-mono"
                                    :modelValue="JSON.stringify(item.edit_value ?? {})"
                                    :disabled="saving"
                                    @update:modelValue="
                                        (v) => {
                                            try { item.edit_value = JSON.parse(v || '{}'); } catch (_) {}
                                        }
                                    "
                                />

                                <InputText
                                    v-else
                                    v-model="item.edit_value"
                                    class="w-full"
                                    :disabled="saving"
                                />

                                <div v-if="fieldError(item.key)" class="mt-1 text-sm text-red-600">
                                    {{ fieldError(item.key) }}
                                </div>
                            </div>

                            <div class="text-xs text-slate-600 space-y-1">
                                <div><b>Default:</b> {{ JSON.stringify(item.default_value) }}</div>
                                <div><b>App:</b> {{ JSON.stringify(item.app_value) }}</div>
                                <div><b>Company:</b> {{ JSON.stringify(item.company_value) }}</div>
                                <div><b>User:</b> {{ JSON.stringify(item.user_value) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
