const COLOR_MAP = {
    ceo: {
        background: "#dc2626",
        border: "#7f1d1d",
    },
    mid: {
        background: "#2563eb",
        border: "#1e40af",
    },
    staff: {
        background: "#6b7280",
        border: "#374151",
    },
};

const DENSITY_CONFIG = {
    compact: {
        height: "36px",
        fontSize: "11px",
        padding: "10px",
        textMaxWidth: "160px",
    },
    comfortable: {
        height: "44px",
        fontSize: "13px",
        padding: "14px",
        textMaxWidth: "200px",
    },
};

export const resolveOrgNodeRole = (node) => {
    const hasSupervisor = Boolean(node?.has_supervisor);
    const directCount = Number(node?.direct_count ?? 0);

    if (!hasSupervisor) {
        return "ceo";
    }

    if (directCount > 0) {
        return "mid";
    }

    return "staff";
};

export const buildOrgNodeLabel = (node, { showPosition = true } = {}) => {
    const name = String(node?.label ?? "").trim();
    const position = String(node?.position ?? "").trim();
    const directCount = Number(node?.direct_count ?? 0);

    if (!showPosition) {
        return name;
    }

    if (position === "") {
        return name;
    }

    if (directCount > 0) {
        return `${name}\n${position} • ${directCount}`;
    }

    return `${name}\n${position}`;
};

export const estimateOrgNodeWidth = (node, { showPosition = true, density = "comfortable" } = {}) => {
    const label = buildOrgNodeLabel(node, { showPosition });
    const lines = label.split("\n");
    const longest = lines.reduce((max, line) => Math.max(max, line.length), 0);
    const perChar = density === "compact" ? 6.2 : 6.8;
    const horizontalPadding = density === "compact" ? 34 : 42;
    const minWidth = density === "compact" ? 110 : 130;
    const maxWidth = density === "compact" ? 240 : 300;
    const calculated = Math.round(longest * perChar + horizontalPadding);

    return Math.max(minWidth, Math.min(maxWidth, calculated));
};

export const buildOrgHierarchyStyles = ({ density = "comfortable" } = {}) => {
    const cfg = DENSITY_CONFIG[density] ?? DENSITY_CONFIG.comfortable;

    return [
        {
            selector: "node",
            style: {
                shape: "round-rectangle",
                label: "data(display_label)",
                width: "data(node_width)",
                height: cfg.height,
                padding: cfg.padding,
                "text-wrap": "wrap",
                "text-max-width": cfg.textMaxWidth,
                "text-valign": "center",
                "text-halign": "center",
                "font-size": cfg.fontSize,
                "font-weight": 500,
                color: "#ffffff",
                "background-color": COLOR_MAP.staff.background,
                "border-color": COLOR_MAP.staff.border,
                "border-width": 2,
                "text-outline-width": 0,
                "transition-property": "background-color border-width border-color",
                "transition-duration": "120ms",
            },
        },
        {
            selector: 'node[role = "ceo"]',
            style: {
                "background-color": COLOR_MAP.ceo.background,
                "border-color": COLOR_MAP.ceo.border,
            },
        },
        {
            selector: 'node[role = "mid"]',
            style: {
                "background-color": COLOR_MAP.mid.background,
                "border-color": COLOR_MAP.mid.border,
            },
        },
        {
            selector: 'node[role = "staff"]',
            style: {
                "background-color": COLOR_MAP.staff.background,
                "border-color": COLOR_MAP.staff.border,
            },
        },
        {
            selector: "node[root]",
            style: {
                "border-width": 3,
                "overlay-color": "#60a5fa",
                "overlay-opacity": 0.12,
                "overlay-padding": "8px",
            },
        },
        {
            selector: "node.is-hovered",
            style: {
                "border-width": 3,
                "overlay-color": "#ffffff",
                "overlay-opacity": 0.14,
                "overlay-padding": "6px",
            },
        },
        {
            selector: "node:selected",
            style: {
                "border-width": 4,
                "border-color": "#22d3ee",
                "font-weight": 700,
                "overlay-color": "#22d3ee",
                "overlay-opacity": 0.2,
                "overlay-padding": "10px",
            },
        },
        {
            selector: "edge",
            style: {
                "curve-style": "bezier",
                "target-arrow-shape": "triangle",
                width: 1.5,
                "line-color": "#9ca3af",
                "target-arrow-color": "#9ca3af",
            },
        },
    ];
};
