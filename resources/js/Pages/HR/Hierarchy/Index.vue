<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OrgHierarchyCytoscape from "@/Components/Org/OrgHierarchyCytoscape.vue";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import Button from "primevue/button";
import Card from "primevue/card";
import DatePicker from "primevue/datepicker";
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

const companyId = ref(Number(props.company_id || 0) || null);
const atDate = ref(props.at_date ? new Date(props.at_date) : new Date());
const currentRootId = ref(null);
const rootStack = ref([]);
const breadcrumbs = ref([]);
const detailNode = ref(null);

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

const breadcrumbPath = computed(() => {
    const base = [{ id: null, label: "CEO" }];
    return base.concat(breadcrumbs.value);
});

const canGoBack = computed(() => rootStack.value.length > 0);

const findNodeById = (id) => nodes.value.find((row) => Number(row.id) === Number(id)) ?? null;

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

const drillDown = (nodeId) => {
    const nextId = Number(nodeId);
    if (!nextId || nextId === currentRootId.value) {
        return;
    }

    const clicked = findNodeById(nextId);
    if (!clicked || Number(clicked.direct_count ?? 0) <= 0) {
        detailNode.value = clicked;
        return;
    }

    if (currentRootId.value !== null) {
        rootStack.value.push(currentRootId.value);
    }

    breadcrumbs.value.push({
        id: nextId,
        label: clicked.label ?? `#${nextId}`,
    });

    currentRootId.value = nextId;
    fetchGraph();
};

const goBack = () => {
    if (!canGoBack.value) {
        return;
    }

    rootStack.value.pop();
    breadcrumbs.value.pop();
    currentRootId.value = rootStack.value.length > 0 ? rootStack.value[rootStack.value.length - 1] : null;
    fetchGraph();
};

const setRootFromBreadcrumb = (index) => {
    if (index <= 0) {
        currentRootId.value = null;
        rootStack.value = [];
        breadcrumbs.value = [];
        fetchGraph();
        return;
    }

    const crumb = breadcrumbs.value[index - 1] ?? null;
    if (!crumb) {
        return;
    }

    currentRootId.value = Number(crumb.id);
    breadcrumbs.value = breadcrumbs.value.slice(0, index);
    rootStack.value = breadcrumbs.value.map((row) => Number(row.id));
    fetchGraph();
};

const onNodeHover = (nodeId) => {
    const hovered = findNodeById(nodeId);
    if (hovered) {
        detailNode.value = hovered;
    }
};

watch(companyId, () => {
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

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                            <Button
                                label="Vissza"
                                icon="pi pi-arrow-left"
                                severity="secondary"
                                :disabled="!canGoBack || loading"
                                class="w-auto min-w-fit inline-flex items-center gap-2 px-3"
                                @click="goBack"
                            />

                            <div class="min-w-0 flex-1 truncate text-sm text-slate-600">
                                <span class="font-semibold">Útvonal:</span>
                                <span class="ml-2 inline-flex flex-wrap items-center gap-1 align-middle">
                                    <button
                                        v-for="(crumb, index) in breadcrumbPath"
                                        :key="`${crumb.id ?? 'ceo'}-${index}`"
                                        type="button"
                                        class="max-w-[220px] truncate rounded border border-slate-200 px-2 py-1 text-slate-700 hover:bg-slate-50"
                                        @click="setRootFromBreadcrumb(index)"
                                    >
                                        {{ crumb.label }}
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <DatePicker v-model="atDate" dateFormat="yy-mm-dd" showIcon class="w-[180px]" />

                            <Button
                                icon="pi pi-refresh"
                                text
                                rounded
                                :disabled="loading"
                                :loading="loading"
                                v-tooltip="'Frissítés'"
                                aria-label="Frissítés"
                                class="w-auto min-w-fit inline-flex items-center justify-center px-3"
                                @click="fetchGraph"
                            />
                        </div>
                    </div>
                </template>
            </Card>

            <OrgHierarchyCytoscape
                :nodes="nodes"
                :edges="edges"
                :root-id="meta.root_id"
                mode="explorer"
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
