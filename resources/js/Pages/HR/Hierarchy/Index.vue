<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OrgHierarchyCytoscape from "@/Components/Org/OrgHierarchyCytoscape.vue";
import HierarchyMoveDialog from "@/Components/Org/HierarchyMoveDialog.vue";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import EmployeeSelector from "@/Components/Selectors/EmployeeSelector.vue";
import Button from "primevue/button";
import Card from "primevue/card";
import DatePicker from "primevue/datepicker";
import Dialog from "primevue/dialog";
import Message from "primevue/message";
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
    ui_settings: {
        type: Object,
        default: () => ({
            view_mode: "explorer",
            density: "comfortable",
            show_position: true,
        }),
    },
});

const toast = useToast();
const loading = ref(false);
const selectedEmployeeId = ref(null);
const selectedNode = ref(null);
const highlightedEmployeeId = ref(null);
const actionMenuVisible = ref(false);
const actionMenuPosition = ref({ x: 0, y: 0 });
const moveDialogVisible = ref(false);
const moveMode = ref("employee_only");
const integrityDialogVisible = ref(false);
const integrityLoading = ref(false);
const integrityReport = ref(null);

const normalizeViewModeValue = (value) => (value === "network" ? "network" : "explorer");
const normalizeDensityValue = (value) => (value === "compact" ? "compact" : "comfortable");
const normalizeBoolValue = (value) => Boolean(value);

const companyId = ref(Number(props.company_id || 0) || null);
const atDate = ref(props.at_date ? new Date(props.at_date) : new Date());
const currentRootId = ref(null);
const rootStack = ref([]);
const breadcrumbs = ref([]);
const detailNode = ref(null);
const viewMode = ref(normalizeViewModeValue(props.ui_settings?.view_mode));
const density = ref(normalizeDensityValue(props.ui_settings?.density));
const showPosition = ref(normalizeBoolValue(props.ui_settings?.show_position ?? true));

const viewModeOptions = [
    { label: "Explorer", value: "explorer" },
    { label: "Network", value: "network" },
];
const densityOptions = [
    { label: "Compact", value: "compact" },
    { label: "Comfortable", value: "comfortable" },
];
const positionOptions = [
    { label: "Pozíció: Ki", value: false },
    { label: "Pozíció: Be", value: true },
];

const nodes = ref([]);
const edges = ref([]);
const meta = ref({ root_id: null, company_id: null, at_date: null, depth: 1, empty: true });

const todayYmd = computed(() => {
    const value = atDate.value instanceof Date ? atDate.value : new Date();
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`;
});

const refreshTooltip = computed(() => (loading.value ? "Frissítés folyamatban..." : "Frissítés"));
const canGoBack = computed(() => rootStack.value.length > 1);
const graphDepth = computed(() => (viewMode.value === "network" ? 2 : 1));
const normalizedViewMode = computed(() => normalizeViewModeValue(viewMode.value));
const normalizedDensity = computed(() => normalizeDensityValue(density.value));
const normalizedShowPosition = computed(() => normalizeBoolValue(showPosition.value));

const actionMenuItems = computed(() => {
    const node = selectedNode.value;
    if (!node) {
        return [];
    }

    const hasSubordinates = Number(node.direct_count ?? 0) > 0;

    return [
        {
            key: "employee_only",
            label: "Áthelyezés",
            icon: "pi pi-user-edit",
            visible: true,
        },
        {
            key: "leader_with_subordinates",
            label: "Áthelyezés csapattal",
            icon: "pi pi-users",
            visible: hasSubordinates,
        },
        {
            key: "leader_without_subordinates",
            label: "Áthelyezés csapat nélkül",
            icon: "pi pi-user-minus",
            visible: hasSubordinates,
        },
        {
            key: "move_subordinates_only",
            label: "Beosztottak áthelyezése",
            icon: "pi pi-share-alt",
            visible: hasSubordinates,
        },
        {
            key: "integrity",
            label: "Integritás ellenőrzés",
            icon: "pi pi-shield",
            visible: true,
        },
    ].filter((item) => item.visible);
});

let settingsSaveTimer = null;
let settingsInitialized = false;

const findNodeById = (id) => nodes.value.find((row) => Number(row.id) === Number(id)) ?? null;

const shortLabel = (label) => {
    const text = String(label ?? "").trim();
    return text.length <= 20 ? text : `${text.slice(0, 19)}…`;
};

const syncStack = () => {
    rootStack.value = breadcrumbs.value.map((row) => Number(row.id));
};

const hideActionMenu = () => {
    actionMenuVisible.value = false;
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
            depth: String(graphDepth.value),
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

        if (selectedNode.value) {
            selectedNode.value = findNodeById(selectedNode.value.id);
            detailNode.value = selectedNode.value;
        }
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

const saveDesignSettingsNow = async () => {
    if (!companyId.value) {
        return;
    }

    try {
        const response = await csrfFetch(route("org.hierarchy.design_settings.save"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
            },
            body: JSON.stringify({
                company_id: Number(companyId.value),
                view_mode: normalizedViewMode.value,
                density: normalizedDensity.value,
                show_position: normalizedShowPosition.value,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload?.message || "A hierarchia UI beállítás mentése sikertelen.");
        }
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error instanceof Error ? error.message : "A hierarchia UI beállítás mentése sikertelen.",
            life: 3500,
        });
    }
};

const queueDesignSettingsSave = () => {
    if (!settingsInitialized) {
        return;
    }

    if (settingsSaveTimer !== null) {
        clearTimeout(settingsSaveTimer);
    }

    settingsSaveTimer = setTimeout(() => {
        saveDesignSettingsNow();
    }, 300);
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

const focusEmployee = async (employeeId) => {
    if (!employeeId) {
        return;
    }

    try {
        const pathRows = await fetchPath(employeeId);
        if (pathRows.length > 0) {
            breadcrumbs.value = pathRows.map((row) => ({
                id: Number(row.id),
                label: String(row.label ?? `#${row.id}`),
            }));
            syncStack();
        }

        currentRootId.value = employeeId;
        await fetchGraph();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error instanceof Error ? error.message : "A kiválasztott dolgozó fókuszálása sikertelen.",
            life: 4000,
        });
    }
};

const highlightEmployee = (employeeId) => {
    const nextId = Number(employeeId || 0);
    highlightedEmployeeId.value = Number.isFinite(nextId) && nextId > 0 ? nextId : null;
};

const refreshGraph = async () => {
    await fetchGraph();
    if (selectedNode.value?.id) {
        highlightEmployee(selectedNode.value.id);
    }
};

const setSelectedNode = (nodeId) => {
    const node = findNodeById(nodeId);
    selectedNode.value = node;
    detailNode.value = node;

    if (node) {
        selectedEmployeeId.value = Number(node.id);
        highlightEmployee(node.id);
    }
};

const openNodeMenu = (payload) => {
    const nodeId = Number(payload?.nodeId ?? 0);
    if (!nodeId) {
        return;
    }

    setSelectedNode(nodeId);
    actionMenuPosition.value = {
        x: Number(payload?.originalEvent?.clientX ?? payload?.renderedPosition?.x ?? 0),
        y: Number(payload?.originalEvent?.clientY ?? payload?.renderedPosition?.y ?? 0),
    };
    actionMenuVisible.value = true;
};

const openMoveDialog = (mode, node = selectedNode.value) => {
    if (!node) {
        return;
    }

    moveMode.value = mode;
    selectedNode.value = node;
    detailNode.value = node;
    moveDialogVisible.value = true;
    hideActionMenu();
};

const closeMoveDialog = () => {
    moveDialogVisible.value = false;
};

const onEmployeeSelected = async (employee) => {
    const selectedId = Number(typeof employee === "object" && employee !== null ? employee.id : employee);

    if (!Number.isFinite(selectedId) || selectedId <= 0) {
        currentRootId.value = null;
        breadcrumbs.value = [];
        rootStack.value = [];
        selectedNode.value = null;
        detailNode.value = null;
        highlightEmployee(null);
        fetchGraph();
        return;
    }

    await focusEmployee(selectedId);
    setSelectedNode(selectedId);
};

const drillDown = (nodeId) => {
    const nextId = Number(nodeId ?? 0);
    if (!nextId) {
        return;
    }

    const clicked = findNodeById(nextId);
    if (!clicked) {
        return;
    }

    setSelectedNode(nextId);

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

const onNodeClick = (payload) => {
    const nodeId = Number(payload?.nodeId ?? 0);
    if (!nodeId) {
        return;
    }

    hideActionMenu();
    drillDown(nodeId);
};

const onNodeContextMenu = (payload) => {
    payload?.originalEvent?.preventDefault?.();
    openNodeMenu(payload);
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

const runIntegrity = async () => {
    if (!companyId.value) {
        return;
    }

    integrityLoading.value = true;
    try {
        const params = new URLSearchParams({
            company_id: String(companyId.value),
            at_date: todayYmd.value,
        });
        const response = await csrfFetch(`${route("org.hierarchy.integrity")}?${params.toString()}`, {
            method: "GET",
            headers: { Accept: "application/json" },
        });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || "Integritás riport lekérése sikertelen.");
        }
        integrityReport.value = payload?.data ?? null;
        integrityDialogVisible.value = true;
        hideActionMenu();
    } catch (error) {
        toast.add({
            severity: "error",
            summary: "Hiba",
            detail: error instanceof Error ? error.message : "Integritás riport lekérése sikertelen.",
            life: 4000,
        });
    } finally {
        integrityLoading.value = false;
    }
};

const onNodeMenuAction = async (item) => {
    if (item.key === "integrity") {
        await runIntegrity();
        return;
    }

    openMoveDialog(item.key, selectedNode.value);
};

const onMoveDone = async (result) => {
    toast.add({
        severity: "success",
        summary: "Sikeres művelet",
        detail: "A hierarchia módosítása mentésre került.",
        life: 3000,
    });

    const focusId = Number(result?.new_root_id ?? selectedNode.value?.id ?? 0) || null;
    if (focusId) {
        await focusEmployee(focusId);
        highlightEmployee(focusId);
        setSelectedNode(focusId);
    } else {
        await refreshGraph();
    }
};

const onDocumentPointerDown = (event) => {
    if (!(event.target instanceof Element)) {
        hideActionMenu();
        return;
    }

    if (event.target.closest("[data-hierarchy-node-menu]")) {
        return;
    }

    hideActionMenu();
};

watch(companyId, () => {
    selectedEmployeeId.value = null;
    selectedNode.value = null;
    detailNode.value = null;
    currentRootId.value = null;
    rootStack.value = [];
    breadcrumbs.value = [];
    highlightEmployee(null);
    fetchGraph();
});

watch(todayYmd, () => {
    fetchGraph();
});

watch(
    [normalizedViewMode, normalizedDensity, normalizedShowPosition],
    () => {
        queueDesignSettingsSave();
    },
);

watch(normalizedViewMode, () => {
    fetchGraph();
});

onMounted(() => {
    settingsInitialized = true;
    document.addEventListener("pointerdown", onDocumentPointerDown);
    fetchGraph();
});

onBeforeUnmount(() => {
    if (settingsSaveTimer !== null) {
        clearTimeout(settingsSaveTimer);
        settingsSaveTimer = null;
    }

    document.removeEventListener("pointerdown", onDocumentPointerDown);
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
                                    :server-search="true"
                                    :disabled="loading || !companyId"
                                    placeholder="Dolgozó keresése..."
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
                            <SelectButton
                                v-model="density"
                                :options="densityOptions"
                                optionLabel="label"
                                optionValue="value"
                                :disabled="loading"
                            />
                            <SelectButton
                                v-model="showPosition"
                                :options="positionOptions"
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
                                @click="refreshGraph"
                            />
                            <Button
                                icon="pi pi-shield"
                                label="Integritás"
                                severity="secondary"
                                :loading="integrityLoading"
                                :disabled="loading || !companyId"
                                class="w-auto min-w-fit inline-flex items-center gap-2 px-3"
                                @click="runIntegrity"
                            />
                        </div>
                    </div>

                    <ProgressBar v-if="loading" mode="indeterminate" style="height: 4px" class="mt-3" />
                </template>
            </Card>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <div class="relative">
                    <OrgHierarchyCytoscape
                        :nodes="nodes"
                        :edges="edges"
                        :root-id="meta.root_id"
                        :mode="normalizedViewMode"
                        :loading="loading"
                        :density="normalizedDensity"
                        :show-position="normalizedShowPosition"
                        :highlighted-employee-id="highlightedEmployeeId"
                        @nodeClick="onNodeClick"
                        @nodeContext="onNodeContextMenu"
                    />

                    <div
                        v-if="actionMenuVisible && selectedNode"
                        data-hierarchy-node-menu
                        class="fixed z-50 w-72 rounded-2xl border border-slate-200 bg-white p-2 shadow-2xl"
                        :style="{ left: `${actionMenuPosition.x}px`, top: `${actionMenuPosition.y}px` }"
                        @pointerdown.stop
                    >
                        <div class="border-b border-slate-100 px-3 py-2">
                            <div class="truncate text-sm font-semibold text-slate-800">{{ selectedNode.label }}</div>
                            <div class="truncate text-xs text-slate-500">{{ selectedNode.position || "Pozíció nélkül" }}</div>
                        </div>
                        <button
                            v-for="item in actionMenuItems"
                            :key="item.key"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                            @click="onNodeMenuAction(item)"
                        >
                            <i :class="item.icon" class="text-slate-500" />
                            <span>{{ item.label }}</span>
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <Card>
                        <template #title>Gyorsműveletek</template>
                        <template #content>
                            <div class="space-y-3">
                                <Message v-if="!selectedNode" severity="info" :closable="false">
                                    Válassz ki egy node-ot a gráfon a műveletekhez.
                                </Message>
                                <div v-else class="space-y-3">
                                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Kiválasztott</div>
                                        <div class="mt-1 text-sm font-medium text-slate-800">{{ selectedNode.label }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ selectedNode.position || "-" }}</div>
                                    </div>
                                    <Button
                                        label="Kiválasztott dolgozó áthelyezése"
                                        icon="pi pi-user-edit"
                                        class="w-full"
                                        :disabled="!selectedNode"
                                        @click="openMoveDialog('employee_only', selectedNode)"
                                    />
                                    <Button
                                        label="Integritás ellenőrzése"
                                        icon="pi pi-shield"
                                        severity="secondary"
                                        class="w-full"
                                        :loading="integrityLoading"
                                        @click="runIntegrity"
                                    />
                                    <Button
                                        label="Fókusz a kiválasztottra"
                                        icon="pi pi-crosshairs"
                                        severity="secondary"
                                        outlined
                                        class="w-full"
                                        @click="focusEmployee(selectedNode.id)"
                                    />
                                </div>
                            </div>
                        </template>
                    </Card>

                    <Card>
                        <template #title>Részletek</template>
                        <template #content>
                            <div v-if="detailNode" class="grid grid-cols-1 gap-2 text-sm text-slate-700">
                                <div><span class="font-semibold">Név:</span> {{ detailNode.label }}</div>
                                <div><span class="font-semibold">Pozíció:</span> {{ detailNode.position || "-" }}</div>
                                <div><span class="font-semibold">Org szint:</span> {{ detailNode.org_level }}</div>
                                <div><span class="font-semibold">Közvetlen beosztott:</span> {{ detailNode.direct_count }}</div>
                            </div>
                            <p v-else class="text-sm text-slate-500">Kattints vagy jobb klikkelj egy node-ra a műveletekhez.</p>
                        </template>
                    </Card>
                </div>
            </div>
        </div>

        <HierarchyMoveDialog
            v-model:visible="moveDialogVisible"
            :mode="moveMode"
            :company-id="Number(companyId || 0)"
            :employee-id="Number(selectedNode?.id || 0)"
            :employee-label="selectedNode?.label || ''"
            :current-supervisor-id="null"
            :at-date="todayYmd"
            :default-effective-from="todayYmd"
            @moved="onMoveDone"
            @update:visible="moveDialogVisible = $event"
        />

        <Dialog
            v-model:visible="integrityDialogVisible"
            modal
            :draggable="false"
            :style="{ width: '52rem', maxWidth: '96vw' }"
            header="Hierarchia integritás"
        >
            <div v-if="integrityReport" class="space-y-3">
                <Message :severity="integrityReport.ok ? 'success' : 'warn'" :closable="false">
                    {{ integrityReport.ok ? "A hierarchia konzisztens." : "Integritási problémák találhatók." }}
                </Message>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="rounded border border-slate-200 p-3 text-sm">
                        <div class="font-semibold">Cycle</div>
                        <div>{{ integrityReport.issues?.cycles?.length || 0 }}</div>
                    </div>
                    <div class="rounded border border-slate-200 p-3 text-sm">
                        <div class="font-semibold">Overlap</div>
                        <div>{{ integrityReport.issues?.overlaps?.length || 0 }}</div>
                    </div>
                    <div class="rounded border border-slate-200 p-3 text-sm">
                        <div class="font-semibold">Missing supervisor</div>
                        <div>{{ integrityReport.issues?.missing_supervisor?.length || 0 }}</div>
                    </div>
                    <div class="rounded border border-slate-200 p-3 text-sm">
                        <div class="font-semibold">Multiple active</div>
                        <div>{{ integrityReport.issues?.multiple_active?.length || 0 }}</div>
                    </div>
                    <div class="rounded border border-slate-200 p-3 text-sm md:col-span-2">
                        <div class="font-semibold">CEO has supervisor</div>
                        <div>{{ integrityReport.issues?.ceo_has_supervisor?.length || 0 }}</div>
                    </div>
                </div>
            </div>
        </Dialog>
    </AuthenticatedLayout>
</template>
