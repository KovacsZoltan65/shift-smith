<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OrgHierarchyCytoscape from "@/Components/Org/OrgHierarchyCytoscape.vue";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import Button from "primevue/button";
import Card from "primevue/card";
import DatePicker from "primevue/datepicker";
import ProgressBar from "primevue/progressbar";
import SelectButton from "primevue/selectbutton";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    title: { type: String, default: "Szervezeti hierarchia" },
    company_id: { type: Number, required: true },
    companies: { type: Array, default: () => [] },
    at_date: { type: String, default: null },
});

const toast = useToast();
const loading = ref(false);
const selectedEmployeeId = ref(null);

const companyId = ref(Number(props.company_id || 0) || null);
const atDate = ref(props.at_date ? new Date(props.at_date) : new Date());
const currentRootId = ref(null);
const rootStack = ref([]);
const breadcrumbs = ref([]);
const detailNode = ref(null);
const viewMode = ref("explorer");

const viewModeOptions = [
    { label: "Explorer", value: "explorer" },
    { label: "Network", value: "network" },
];

const nodes = ref([]);
const edges = ref([]);
const meta = ref({ root_id: null, company_id: null, at_date: null, depth: 1, empty: true });

const todayYmd = computed(() => {
    const value = atDate.value instanceof Date ? atDate.value : new Date();
    const y = value.getFullYear();
    const m = String(value.getMonth() + 1).padStart(2, "0");
    const d = String(value.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
});

const refreshTooltip = computed(() => (loading.value ? "Frissítés folyamatban..." : "Frissítés"));
const canGoBack = computed(() => rootStack.value.length > 1);

const findNodeById = (id) => nodes.value.find((row) => Number(row.id) === Number(id)) ?? null;

const shortLabel = (label) => {
    const text = String(label ?? "").trim();
    if (text.length <= 20) {
        return text;
    }

    return `${text.slice(0, 19)}…`;
};

const syncStack = () => {
    rootStack.value = breadcrumbs.value.map((row) => Number(row.id));
};

const ensureBreadcrumbRoot = () => {
    const rootId = Number(meta.value?.root_id ?? 0);
    if (!Number.isFinite(rootId) || rootId <= 0) {
        return;
    }

    const rootNode = findNodeById(rootId);
    const rootLabel = rootNode?.label ?? `#${rootId}`;

    if (breadcrumbs.value.length === 0) {
        breadcrumbs.value = [{ id: rootId, label: rootLabel }];
        syncStack();
        return;
    }

    const existsAt = breadcrumbs.value.findIndex((row) => Number(row.id) === rootId);
    if (existsAt >= 0) {
        breadcrumbs.value = breadcrumbs.value.slice(0, existsAt + 1);
        breadcrumbs.value[existsAt] = { id: rootId, label: rootLabel };
    } else {
        breadcrumbs.value = [...breadcrumbs.value, { id: rootId, label: rootLabel }];
    }

    syncStack();
};

const fetchGraph = async () => {
    if (!companyId.value) {
        nodes.value = [];
        edges.value = [];
        meta.value = { root_id: null, company_id: null, at_date: todayYmd.value, depth: 1, empty: true };
        return;
    }

    loading.value = true;

    try {
        const params = new URLSearchParams({
            company_id: String(companyId.value),
            at_date: todayYmd.value,
            depth: "1",
        });

        if (currentRootId.value !== null) {
            params.set("root_employee_id", String(currentRootId.value));
        }

        const response = await csrfFetch(`${route("org.hierarchy.graph")}?${params.toString()}`, {
            method: "GET",
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || "A hierarchia lekérése sikertelen.");
        }

        const data = payload?.data ?? {};
        nodes.value = Array.isArray(data.nodes) ? data.nodes : [];
        edges.value = Array.isArray(data.edges) ? data.edges : [];
        meta.value = data.meta ?? meta.value;
        ensureBreadcrumbRoot();
    } catch (error) {
        nodes.value = [];
        edges.value = [];
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error instanceof Error ? error.message : "A hierarchia lekérése sikertelen.",
            life: 4000,
        });
    } finally {
        loading.value = false;
    }
};

const fetchPath = async (employeeId) => {
    if (!companyId.value || !employeeId) {
        return [];
    }

    const params = new URLSearchParams({
        company_id: String(companyId.value),
        employee_id: String(employeeId),
        at_date: todayYmd.value,
    });

    const response = await csrfFetch(`${route("org.hierarchy.path")}?${params.toString()}`, {
        method: "GET",
    });

    const payload = await response.json();
    if (!response.ok) {
        throw new Error(payload?.message || "Az útvonal lekérése sikertelen.");
    }

    return Array.isArray(payload?.data) ? payload.data : [];
};

const drillDown = (nodeId) => {
    const nextId = Number(nodeId);
    if (!nextId) {
        return;
    }

    const clicked = findNodeById(nextId);
    if (!clicked) {
        return;
    }

    detailNode.value = clicked;
    if (Number(clicked.direct_count ?? 0) <= 0) {
        return;
    }

    const existingIndex = breadcrumbs.value.findIndex((row) => Number(row.id) === nextId);
    if (existingIndex >= 0) {
        breadcrumbs.value = breadcrumbs.value.slice(0, existingIndex + 1);
    } else {
        breadcrumbs.value = [...breadcrumbs.value, { id: nextId, label: clicked.label ?? `#${nextId}` }];
    }

    currentRootId.value = nextId;
    syncStack();
    fetchGraph();
};

const goBack = () => {
    if (!canGoBack.value) {
        return;
    }

    breadcrumbs.value = breadcrumbs.value.slice(0, breadcrumbs.value.length - 1);
    const last = breadcrumbs.value[breadcrumbs.value.length - 1] ?? null;
    currentRootId.value = last ? Number(last.id) : null;
    syncStack();
    fetchGraph();
};

const setRootFromBreadcrumb = (index) => {
    if (index < 0 || index >= breadcrumbs.value.length) {
        return;
    }

    const crumb = breadcrumbs.value[index];
    breadcrumbs.value = breadcrumbs.value.slice(0, index + 1);
    currentRootId.value = Number(crumb.id);
    syncStack();
    fetchGraph();
};

const onEmployeeSelected = async (employee) => {
    const selectedId = Number(
        typeof employee === "object" && employee !== null ? employee.id : employee,
    );

    if (!Number.isFinite(selectedId) || selectedId <= 0) {
        currentRootId.value = null;
        breadcrumbs.value = [];
        rootStack.value = [];
        fetchGraph();
        return;
    }

    try {
        const pathRows = await fetchPath(selectedId);
        if (pathRows.length > 0) {
            breadcrumbs.value = pathRows.map((row) => ({
                id: Number(row.id),
                label: String(row.label ?? `#${row.id}`),
            }));
            syncStack();
        }

        currentRootId.value = selectedId;
        await fetchGraph();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error instanceof Error ? error.message : "A kiválasztott dolgozó betöltése sikertelen.",
            life: 4000,
        });
    }
};

const onNodeHover = (nodeId) => {
    const hovered = findNodeById(nodeId);
    if (hovered) {
        detailNode.value = hovered;
    }
};

watch(companyId, () => {
    selectedEmployeeId.value = null;
    currentRootId.value = null;
    rootStack.value = [];
    breadcrumbs.value = [];
    fetchGraph();
});

watch(todayYmd, () => {
    fetchGraph();
});

onMounted(() => {
    fetchGraph();
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <Toast />

        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
        </template>

        <div class="mx-auto max-w-[1680px] p-4 sm:p-6 lg:p-8">
            <Card class="mb-4">
                <template #content>
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cég</label>
                            <CompanySelector v-model="companyId" :options="companies" />
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                            <Button
                                label="Vissza"
                                icon="pi pi-arrow-left"
                                severity="secondary"
                                :disabled="!canGoBack || loading"
                                class="w-auto min-w-fit inline-flex items-center gap-2 px-3"
                                @click="goBack"
                            />

                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                <Tag
                                    v-for="(crumb, index) in breadcrumbs"
                                    :key="`${crumb.id}-${index}`"
                                    :severity="index === breadcrumbs.length - 1 ? 'primary' : 'secondary'"
                                    :value="shortLabel(crumb.label)"
                                    class="max-w-[220px] cursor-pointer truncate"
                                    v-tooltip="crumb.label"
                                    @click="setRootFromBreadcrumb(index)"
                                />
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <div class="w-[280px] min-w-[220px]">
                                <EmployeeSelector
                                    v-model="selectedEmployeeId"
                                    :company-id="companyId"
                                    :server-search="false"
                                    :filter="true"
                                    :disabled="loading || !companyId"
                                    placeholder="Dolgozó kiválasztása..."
                                    @selected="onEmployeeSelected"
                                />
                            </div>

                            <DatePicker
                                v-model="atDate"
                                dateFormat="yy-mm-dd"
                                showIcon
                                class="w-[180px]"
                                :disabled="loading"
                            />

                            <SelectButton
                                v-model="viewMode"
                                :options="viewModeOptions"
                                optionLabel="label"
                                optionValue="value"
                                :disabled="loading"
                            />

                            <Button
                                :icon="loading ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'"
                                text
                                rounded
                                :disabled="loading"
                                v-tooltip="refreshTooltip"
                                aria-label="Frissítés"
                                class="w-auto min-w-fit inline-flex items-center justify-center px-3"
                                @click="fetchGraph"
                            />
                        </div>
                    </div>

                    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mt-3" />
                </template>
            </Card>

            <OrgHierarchyCytoscape
                :nodes="nodes"
                :edges="edges"
                :root-id="meta.root_id"
                :mode="viewMode"
                :loading="loading"
                @nodeClick="drillDown"
                @nodeHover="onNodeHover"
            />

            <Card class="mt-4">
                <template #title>Részletek</template>
                <template #content>
                    <div v-if="detailNode" class="grid grid-cols-1 gap-2 text-sm text-slate-700 md:grid-cols-2">
                        <div><span class="font-semibold">Név:</span> {{ detailNode.label }}</div>
                        <div><span class="font-semibold">Pozíció:</span> {{ detailNode.position || "-" }}</div>
                        <div><span class="font-semibold">Org szint:</span> {{ detailNode.org_level }}</div>
                        <div><span class="font-semibold">Közvetlen beosztott:</span> {{ detailNode.direct_count }}</div>
                    </div>
                    <p v-else class="text-sm text-slate-500">Kattints egy node-ra a részletekhez.</p>
                </template>
            </Card>
        </div>
    </AuthenticatedLayout>
</template>
