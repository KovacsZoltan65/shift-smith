import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";

export function useAppMenu() {
    const page = usePage();

    // Inertia props lehet ref is (page.props.value), de lehet sima object is.
    const props = computed(() => page.props?.value ?? page.props ?? {});

    const menu = computed(() => [
        {
            title: "Adminisztráció",
            items: [
                {
                    title: "Felhasználók",
                    route: "users.index",
                    key: "users.index",
                    can: "users.viewAny",
                },
                {
                    title: "Cégek",
                    route: "companies.index",
                    key: "companies.index",
                    can: "companies.viewAny",
                },
            ],
        },
        {
            title: "Biztonság",
            items: [
                {
                    title: "Jogok",
                    route: "permissions.index",
                    key: "permissions.index",
                    can: "permissions.viewAny",
                },
                {
                    title: "Szerepkörök",
                    route: "roles.index",
                    key: "roles.index",
                    can: "roles.viewAny",
                },
            ],
        },
        {
            title: "HR",
            items: [
                {
                    title: "Dolgozók",
                    route: "employees.index",
                    key: "employees.index",
                    can: "employees.viewAny",
                },
                {
                    title: "Beosztások",
                    route: "assignments.index",
                    key: "assignments.index",
                    can: "assignments.viewAny",
                },
                {
                    title: "Műszakok",
                    route: "shifts.index",
                    key: "shifts.index",
                    can: "shifts.viewAny",
                },
                {
                    title: "Tervezés",
                    route: "planning.index",
                    key: "planning.index",
                    can: "planning.view",
                },
            ],
        },
        {
            title: "Beállítások",
            items: [
                {
                    title: "Applikáció",
                    route: "settings.app",
                    key: "settings.app",
                    can: "settings.app",
                },
                {
                    title: "Cég",
                    route: "settings.company",
                    key: "settings.company",
                    can: "settings.company",
                },
                {
                    title: "Személy",
                    route: "settings.user",
                    key: "settings.user",
                    can: "settings.user",
                },
            ],
        },
    ]);

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

        // group rendezés: amelyik groupban van "használt" elem, az jöjjön előrébb
        groups.sort((ga, gb) => {
            const aScores = ga.items.map(sortScore).filter((x) => x !== null);
            const bScores = gb.items.map(sortScore).filter((x) => x !== null);

            const aBest = aScores.length ? Math.min(...aScores) : null;
            const bBest = bScores.length ? Math.min(...bScores) : null;

            if (aBest === null && bBest === null) return 0;
            if (aBest === null) return 1;
            if (bBest === null) return -1;
            return aBest - bBest;
        });

        return groups;
    });

    return { menu, filteredMenu, props };
}
