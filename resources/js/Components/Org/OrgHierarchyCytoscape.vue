<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import cytoscape from "cytoscape";
import ProgressSpinner from "primevue/progressspinner";
import {
    buildOrgHierarchyStyles,
    buildOrgNodeLabel,
    estimateOrgNodeWidth,
    resolveOrgNodeRole,
} from "@/Styles/orgHierarchyCytoscapeStyles.js";

const props = defineProps({
    nodes: { type: Array, default: () => [] },
    edges: { type: Array, default: () => [] },
    mode: { type: String, default: "explorer" },
    rootId: { type: [Number, null], default: null },
    loading: { type: Boolean, default: false },
    density: { type: String, default: "comfortable" },
    showPosition: { type: Boolean, default: true },
    highlightedEmployeeId: { type: [Number, null], default: null },
});

const emit = defineEmits(["nodeClick", "nodeHover", "nodeContext"]);

const container = ref(null);
let cy = null;
let renderSequence = 0;
const preventNativeContextMenu = (event) => {
    event.preventDefault();
};

const isEmpty = computed(() => !Array.isArray(props.nodes) || props.nodes.length === 0);

const toElements = () => {
    const mappedNodes = (props.nodes ?? []).map((node) => ({
        data: {
            id: String(node.id),
            label: String(node.label ?? ""),
            display_label: buildOrgNodeLabel(node, { showPosition: props.showPosition }),
            node_width: estimateOrgNodeWidth(node, {
                showPosition: props.showPosition,
                density: props.density,
            }),
            org_level: String(node.org_level ?? "staff"),
            position: node.position ?? null,
            direct_count: Number(node.direct_count ?? 0),
            has_supervisor: Boolean(node.has_supervisor ?? false),
            role: resolveOrgNodeRole(node),
            root:
                Boolean(node.is_root) ||
                (props.rootId !== null &&
                    Number(node.id) === Number(props.rootId)),
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

const styleSheet = computed(() =>
    [
        ...buildOrgHierarchyStyles({
            density: props.density,
            showPosition: props.showPosition,
        }),
        {
            selector: "node.is-highlighted",
            style: {
                "border-width": 4,
                "border-color": "#0f766e",
                "overlay-opacity": 0.15,
                "overlay-color": "#14b8a6",
            },
        },
    ],
);

const syncHighlight = () => {
    if (!cy) {
        return;
    }

    cy.nodes().removeClass("is-highlighted");

    const highlightId = Number(props.highlightedEmployeeId ?? 0);
    if (!Number.isFinite(highlightId) || highlightId <= 0) {
        return;
    }

    const node = cy.getElementById(String(highlightId));
    if (!node || node.empty()) {
        return;
    }

    node.addClass("is-highlighted");
    cy.animate({
        center: { eles: node },
        duration: 250,
    });
};

const render = async () => {
    const seq = ++renderSequence;
    if (!container.value) {
        return;
    }
    await nextTick();
    if (seq !== renderSequence) {
        return;
    }

    if (!cy) {
        cy = cytoscape({
            container: container.value,
            elements: [],
            style: styleSheet.value,
        });

        cy.on("tap", "node", (event) => {
            const data = event.target.data();
            emit("nodeClick", {
                nodeId: Number(data.id),
                renderedPosition: event.target.renderedPosition(),
                originalEvent: event.originalEvent ?? null,
            });
        });

        cy.on("mouseover", "node", (event) => {
            const target = event.target;
            target.addClass("is-hovered");
            const data = target.data();
            emit("nodeHover", Number(data.id));
        });

        cy.on("mouseout", "node", (event) => {
            event.target.removeClass("is-hovered");
        });

        cy.on("cxttap", "node", (event) => {
            const data = event.target.data();
            emit("nodeContext", {
                nodeId: Number(data.id),
                renderedPosition: event.target.renderedPosition(),
                originalEvent: event.originalEvent ?? null,
            });
        });
    }

    cy.style(styleSheet.value).update();
    cy.startBatch();
    cy.elements().remove();
    cy.add(toElements());
    cy.endBatch();

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

    syncHighlight();
};

watch(
    () => [props.nodes, props.edges, props.mode, props.density, props.showPosition],
    () => {
        render();
    },
    { deep: true },
);

watch(
    () => props.highlightedEmployeeId,
    () => {
        syncHighlight();
    },
);

onMounted(() => {
    if (container.value) {
        container.value.addEventListener("contextmenu", preventNativeContextMenu);
    }
    render();
});

onBeforeUnmount(() => {
    if (container.value) {
        container.value.removeEventListener("contextmenu", preventNativeContextMenu);
    }
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
