<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import cytoscape from "cytoscape";

const props = defineProps({
    companyId: { type: Number, required: true },
    initialRootEmployeeId: { type: [Number, null], default: null },
    atDate: { type: String, required: true },
    graph: {
        type: Object,
        default: () => ({ nodes: [], edges: [], meta: {} }),
    },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(["drill", "node-selected"]);

const containerRef = ref(null);
const selectedNode = ref(null);
let cy = null;

const isEmpty = computed(() => !Array.isArray(props.graph?.nodes) || props.graph.nodes.length === 0);

const nodeSize = (directCount) => {
    const base = 46;
    const scaled = Math.min(92, base + Number(directCount || 0) * 5);
    return scaled;
};

const toElements = () => {
    const nodes = Array.isArray(props.graph?.nodes) ? props.graph.nodes : [];
    const edges = Array.isArray(props.graph?.edges) ? props.graph.edges : [];

    return [
        ...nodes.map((node) => ({
            data: {
                id: String(node.id),
                label: String(node.label ?? ""),
                position: node.position ?? null,
                org_level: String(node.org_level ?? "staff"),
                direct_count: Number(node.direct_count ?? 0),
                total_count: Number(node.total_count ?? 0),
                size: nodeSize(node.direct_count),
            },
        })),
        ...edges.map((edge) => ({
            data: {
                id: `${edge.source}-${edge.target}`,
                source: String(edge.source),
                target: String(edge.target),
            },
        })),
    ];
};

const renderGraph = async () => {
    if (!containerRef.value) return;
    await nextTick();

    if (!cy) {
        cy = cytoscape({
            container: containerRef.value,
            elements: [],
            style: [
                {
                    selector: "node",
                    style: {
                        "background-color": "#2563eb",
                        label: "data(label)",
                        color: "#ffffff",
                        "font-size": 11,
                        "text-wrap": "wrap",
                        "text-max-width": 90,
                        "text-valign": "center",
                        "text-halign": "center",
                        width: "data(size)",
                        height: "data(size)",
                        "border-width": 2,
                        "border-color": "#dbeafe",
                    },
                },
                {
                    selector: "node:selected",
                    style: {
                        "background-color": "#1d4ed8",
                        "border-color": "#93c5fd",
                        "border-width": 3,
                    },
                },
                {
                    selector: "edge",
                    style: {
                        width: 2,
                        "line-color": "#94a3b8",
                        "target-arrow-shape": "triangle",
                        "target-arrow-color": "#94a3b8",
                        "curve-style": "bezier",
                    },
                },
            ],
            layout: { name: "concentric", fit: true, padding: 30, avoidOverlap: true },
            wheelSensitivity: 0.2,
        });

        cy.on("tap", "node", (event) => {
            const data = event.target.data();
            selectedNode.value = {
                id: Number(data.id),
                label: data.label,
                position: data.position,
                org_level: data.org_level,
                direct_count: Number(data.direct_count ?? 0),
                total_count: Number(data.total_count ?? 0),
            };
            emit("node-selected", selectedNode.value);

            if (Number(data.direct_count ?? 0) > 0) {
                emit("drill", Number(data.id));
            }
        });
    }

    cy.elements().remove();
    cy.add(toElements());
    cy.layout({
        name: "breadthfirst",
        directed: true,
        spacingFactor: 1.05,
        padding: 36,
        animate: false,
    }).run();

    if (cy.nodes().length > 0) {
        cy.fit(cy.nodes(), 40);
    }
};

watch(
    () => props.graph,
    () => {
        renderGraph();
    },
    { deep: true },
);

onMounted(() => {
    renderGraph();
});

onBeforeUnmount(() => {
    if (cy) {
        cy.destroy();
        cy = null;
    }
});
</script>

<template>
    <div class="grid h-full min-h-[560px] grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
        <div class="relative rounded-lg border border-slate-200 bg-white">
            <div ref="containerRef" class="h-[70vh] min-h-[520px] w-full" />

            <div
                v-if="loading"
                class="absolute inset-0 z-20 flex items-center justify-center bg-white/70 backdrop-blur-[1px]"
            >
                <ProgressSpinner style="width: 48px; height: 48px" strokeWidth="4" />
            </div>

            <div
                v-if="isEmpty && !loading"
                class="absolute inset-0 z-10 flex items-center justify-center text-sm text-slate-500"
            >
                Nincs megjeleníthető hierarchia az adott feltételekkel.
            </div>
        </div>

        <Card class="h-fit">
            <template #title>Node adatok</template>
            <template #content>
                <div v-if="selectedNode" class="space-y-2 text-sm text-slate-700">
                    <div><span class="font-semibold">Név:</span> {{ selectedNode.label }}</div>
                    <div><span class="font-semibold">Pozíció:</span> {{ selectedNode.position || "-" }}</div>
                    <div><span class="font-semibold">Szint:</span> {{ selectedNode.org_level }}</div>
                    <div><span class="font-semibold">Közvetlen beosztott:</span> {{ selectedNode.direct_count }}</div>
                </div>
                <p v-else class="text-sm text-slate-500">
                    Kattints egy node-ra a részletekhez.
                </p>
            </template>
        </Card>
    </div>
</template>
