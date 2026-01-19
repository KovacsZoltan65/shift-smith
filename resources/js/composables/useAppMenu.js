import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { appMenuDefinition } from "@/menu/appMenuDefinition";

export function useAppMenu() {
    const page = usePage();

    // Inertia props lehet ref is (page.props.value), de lehet sima object is.
    const props = computed(() => page.props?.value ?? page.props ?? {});
    const menu = computed(() => appMenuDefinition);

    const filteredMenu = computed(() => {
        const p = props.value;

        const perms = p.auth?.permissions ?? [];
        const roles = p.auth?.roles ?? [];
        const menuOrder = p.menu_order ?? [];

        const isSuperadmin =
            Array.isArray(roles) && roles.includes("superadmin");

        // menu_order -> index map
        const orderIndex = new Map();
        if (Array.isArray(menuOrder)) {
            menuOrder.forEach((k, idx) => orderIndex.set(String(k), idx));
        }

        // UI jogosultság (most egyszerű)
        const canSee = (item) => {
            if (isSuperadmin) return true;
            if (!item.can) return true;
            if (!Array.isArray(perms) || perms.length === 0) return true;
            return perms.includes(item.can);
        };

        // csak akkor "rendez", ha benne van a kulcs a listában.
        const sortScore = (item) => {
            const k = String(item.key ?? item.route ?? "");
            return orderIndex.has(k) ? orderIndex.get(k) : null;
        };

        const groups = menu.value
            .map((group) => {
                const items = (group.items ?? [])
                    .filter(canSee)
                    .slice()
                    .sort((a, b) => {
                        const sa = sortScore(a);
                        const sb = sortScore(b);

                        // ha egyik sincs a menu_order-ben, maradjon az eredeti sorrend
                        if (sa === null && sb === null) return 0;

                        // amelyik benne van, az előre
                        if (sa === null) return 1;
                        if (sb === null) return -1;

                        // mindkettő benne van -> menu_order szerinti sorrend
                        return sa - sb;
                    });

                return { ...group, items };
            })
            .filter((g) => (g.items ?? []).length > 0);

        return groups;
    });

    return { menu, filteredMenu, props };
}
