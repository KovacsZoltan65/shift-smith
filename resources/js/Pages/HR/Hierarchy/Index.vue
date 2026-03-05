<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import OrgHierarchyGraph from "@/Components/Org/OrgHierarchyGraph.vue";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";
import Button from "primevue/button";
import Card from "primevue/card";
import DatePicker from "primevue/datepicker";
import Message from "primevue/message";
import { csrfFetch } from "@/lib/csrfFetch";

const props = defineProps({
    title: { type: String, default: "Szervezeti hierarchia" },
    company_id: { type: Number, required: true },
    companies: { type: Array, default: () => [] },
    at_date: { type: String, default: null },
});

const loading = ref(false);
const error = ref("");

const companyId = ref(Number(props.company_id || 0) || null);
const atDate = ref(props.at_date ? new Date(props.at_date) : new Date());
const rootStack = ref([]);
const currentRootId = ref(null);
const graphData = ref({ nodes: [], edges: [], meta: {} });

const currentAtDateYmd = computed(() => {
    const value = atDate.value instanceof Date ? atDate.value : new Date();
    const y = value.getFullYear();
    const m = String(value.getMonth() + 1).padStart(2, "0");
    const d = String(value.getDate()).padStart(2, "0");
    return `${y}-${m}-${d}`;
});

const canGoBack = computed(() => rootStack.value.length > 0);

const fetchGraph = async () => {
    if (!companyId.value) return;

    loading.value = true;
    error.value = "";

    try {
        const params = new URLSearchParams({
            company_id: String(companyId.value),
            at_date: currentAtDateYmd.value,
        });

        if (currentRootId.value) {
            params.set("root_employee_id", String(currentRootId.value));
        }

        const response = await csrfFetch(`${route("org.hierarchy.graph")}?${params.toString()}`, {
            method: "GET",
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload?.message || "A hierarchia lekérése sikertelen.");
        }

        graphData.value = payload?.data ?? { nodes: [], edges: [], meta: {} };
    } catch (err) {
        error.value = err instanceof Error ? err.message : "Hiba a hierarchia betöltésekor.";
        graphData.value = { nodes: [], edges: [], meta: {} };
    } finally {
        loading.value = false;
    }
};

const drillDown = (nodeId) => {
    if (!nodeId || nodeId === currentRootId.value) return;

    if (currentRootId.value !== null) {
        rootStack.value.push(currentRootId.value);
    }

    currentRootId.value = Number(nodeId);
    fetchGraph();
};

const goBack = () => {
    if (rootStack.value.length === 0) return;
    currentRootId.value = rootStack.value.pop() ?? null;
    fetchGraph();
};

const resetRoot = () => {
    rootStack.value = [];
    currentRootId.value = null;
    fetchGraph();
};

watch(companyId, () => {
    rootStack.value = [];
    currentRootId.value = null;
    fetchGraph();
});

watch(currentAtDateYmd, () => {
    fetchGraph();
});

onMounted(() => {
    fetchGraph();
});
</script>

<template>
    <Head :title="title" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
        </template>

        <div class="mx-auto max-w-[1600px] p-4 sm:p-6 lg:p-8">
            <Card class="mb-4">
                <template #content>
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div class="grid w-full grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cég</label>
                                <CompanySelector v-model="companyId" :options="companies" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Dátum</label>
                                <DatePicker v-model="atDate" dateFormat="yy-mm-dd" showIcon class="w-full" />
                            </div>
                            <div class="flex items-end gap-2">
                                <Button
                                    label="Vissza"
                                    icon="pi pi-arrow-left"
                                    severity="secondary"
                                    :disabled="!canGoBack || loading"
                                    @click="goBack"
                                />
                                <Button
                                    label="CEO gyökér"
                                    icon="pi pi-sitemap"
                                    severity="contrast"
                                    :disabled="loading"
                                    @click="resetRoot"
                                />
                                <Button
                                    label="Frissítés"
                                    icon="pi pi-refresh"
                                    :loading="loading"
                                    @click="fetchGraph"
                                />
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">
                        Kattints egy vezetőre a kibontáshoz. A graf csak az aktuális gyökér és közvetlen beosztottai réteget mutatja.
                    </p>
                </template>
            </Card>

            <Message v-if="error" severity="error" class="mb-4">{{ error }}</Message>

            <OrgHierarchyGraph
                :company-id="Number(companyId || 0)"
                :initial-root-employee-id="currentRootId"
                :at-date="currentAtDateYmd"
                :graph="graphData"
                :loading="loading"
                @drill="drillDown"
            />
        </div>
    </AuthenticatedLayout>
</template>
