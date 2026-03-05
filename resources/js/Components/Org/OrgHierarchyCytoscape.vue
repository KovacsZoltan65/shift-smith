<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import cytoscape from "cytoscape";
import ProgressSpinner from "primevue/progressspinner";

const props = defineProps({
    nodes: { type: Array, default: () => [] },
    edges: { type: Array, default: () => [] },
    mode: { type: String, default: "explorer" },
    rootId: { type: [Number, null], default: null },
    loading: { type: Boolean, default: false },
    density: { type: String, default: "comfortable" },
    showPosition: { type: Boolean, default: true },
});

const emit = defineEmits(["nodeClick", "nodeHover"]);

const container = ref(null);
let cy = null;

const isEmpty = computed(() => !Array.isArray(props.nodes) || props.nodes.length === 0);

const toElements = () => {
    const mappedNodes = (props.nodes ?? []).map((node) => ({
        data: {
            id: String(node.id),
            label: props.showPosition && node.position
                ? `${String(node.label ?? "")}\n${String(node.position)}`
                : String(node.label ?? ""),
            org_level: String(node.org_level ?? "staff"),
            position: node.position ?? null,
            direct_count: Number(node.direct_count ?? 0),
            root:
                props.rootId !== null &&
                Number(node.id) === Number(props.rootId),
        },
    }));

    const mappedEdges = (props.edges ?? []).map((edge) => ({
        data: {
            id: `${edge.source}-${edge.target}`,
            source: String(edge.source),
            target: String(edge.target),
        },
    }));

    return [...mappedNodes, ...mappedEdges];
};

const styleConfig = computed(() => {
    const compact = props.density === "compact";

    return {
        textMaxWidth: compact ? "120px" : "160px",
        height: compact ? "34px" : "44px",
        padding: compact ? "8px" : "14px",
        fontSize: compact ? "11px" : "12px",
    };
});

const styleSheet = () => [
    {
        selector: "node",
        style: {
            shape: "round-rectangle",
            label: "data(label)",
            "text-wrap": "wrap",
            "text-max-width": styleConfig.value.textMaxWidth,
            width: "label",
            height: styleConfig.value.height,
            padding: styleConfig.value.padding,
            "text-valign": "center",
            "text-halign": "center",
            "font-size": styleConfig.value.fontSize,
            "font-weight": 500,
            "background-color": "#2563eb",
            color: "#ffffff",
            "border-width": 2,
            "border-color": "#1e40af",
        },
    },
    {
        selector: "node:hover",
        style: {
            "background-color": "#1d4ed8",
            "border-width": 3,
        },
    },
    {
        selector: "node[root]",
        style: {
            "background-color": "#dc2626",
            "border-color": "#7f1d1d",
        },
    },
    {
        selector: "edge",
        style: {
            "curve-style": "bezier",
            "target-arrow-shape": "triangle",
            "line-color": "#94a3b8",
            "target-arrow-color": "#94a3b8",
        },
    },
];

const render = async () => {
    if (!container.value) {
        return;
    }
    await nextTick();

    if (!cy) {
        cy = cytoscape({
            container: container.value,
            elements: [],
            wheelSensitivity: 0.2,
            style: styleSheet(),
        });

        cy.on("tap", "node", (event) => {
            const data = event.target.data();
            emit("nodeClick", Number(data.id));
        });

        cy.on("mouseover", "node", (event) => {
            const data = event.target.data();
            emit("nodeHover", Number(data.id));
        });
    }

    cy.style(styleSheet());
    cy.elements().remove();
    cy.add(toElements());

    const layout =
        props.mode === "explorer"
            ? {
                  name: "breadthfirst",
                  directed: true,
                  padding: 40,
                  spacingFactor: 1.2,
                  animate: false,
              }
            : {
                  name: "cose",
                  padding: 40,
                  animate: true,
              };

    cy.layout(layout).run();

    if (cy.nodes().length > 0) {
        cy.fit(cy.nodes(), 36);
    }
};

watch(
    () => [props.nodes, props.edges, props.mode, props.density, props.showPosition],
    () => {
        render();
    },
    { deep: true },
);

onMounted(() => {
    render();
});

onBeforeUnmount(() => {
    if (cy) {
        cy.destroy();
        cy = null;
    }
});
</script>

<template>
    <div class="relative rounded-lg border border-slate-200 bg-white">
        <div ref="container" class="h-[72vh] min-h-[520px] w-full" />

        <div
            v-if="loading"
            class="absolute inset-0 z-20 flex items-center justify-center bg-white/70"
        >
            <ProgressSpinner style="width: 46px; height: 46px" strokeWidth="4" />
        </div>

        <div
            v-if="isEmpty && !loading"
            class="absolute inset-0 z-10 flex items-center justify-center text-sm text-slate-500"
        >
            Nincs megjeleníthető hierarchia.
        </div>
    </div>
</template>
