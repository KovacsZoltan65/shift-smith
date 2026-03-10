import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";
import { appMenuDefinition } from "@/menu/appMenuDefinition";

/**
 * A statikus menüdefinícióból tenant- és jogosultságfüggő, lefordított menüfát épít.
 *
 * A composable célja, hogy a render rétegnek már csak a rendezett és deduplikált menüt adja át.
 */
export function useAppMenu() {
    const page = usePage();

    // A page.props Inertia verziótól és adaptertől függően lehet ref vagy plain object is.
    const props = computed(() => page.props?.value ?? page.props ?? {});
    const translateLabel = (item) => {
        if (!item || typeof item !== "object") return item;

        const translatedTitle = item.titleKey
            ? trans(item.titleKey)
            : item.title;

        const translatedItems = Array.isArray(item.items)
            ? item.items.map(translateLabel)
            : item.items;

        return {
            ...item,
            title: translatedTitle,
            ...(Array.isArray(translatedItems) ? { items: translatedItems } : {}),
        };
    };

    const menu = computed(() => appMenuDefinition.map(translateLabel));

    const itemIdentity = (item) =>
        String(item?.route ?? item?.key ?? item?.title ?? "");

    // A duplikációszűrés route/key/title alapján fut, mert ugyanaz az elem több forrásból is bekerülhet.
    const dedupeItems = (items = []) => {
        const seen = new Set();

        return items.reduce((acc, item) => {
            if (!item || typeof item !== "object") {
                return acc;
            }

            const children = Array.isArray(item.items)
                ? dedupeItems(item.items)
                : [];

            const nextItem = Array.isArray(item.items)
                ? { ...item, items: children }
                : item;

            const identity = itemIdentity(nextItem);
            if (!identity || seen.has(identity)) {
                return acc;
            }

            seen.add(identity);
            acc.push(nextItem);

            return acc;
        }, []);
    };

    const filteredMenu = computed(() => {
        const p = props.value;

        const perms = p.auth?.permissions ?? [];
        const roles = p.auth?.roles ?? [];
        const menuOrder = p.menu_order ?? [];

        const isSuperadmin =
            Array.isArray(roles) && roles.includes("superadmin");

        // A backend által küldött menu_order csak a ténylegesen ismert kulcsokra alkalmazható.
        const orderIndex = new Map();
        if (Array.isArray(menuOrder)) {
            menuOrder.forEach((k, idx) => orderIndex.set(String(k), idx));
        }

        // A superadmin minden menüpontot lát, más szerepkörök a permission listából szűrnek.
        const canSee = (item) => {
            if (isSuperadmin) return true;
            if (!item.can) return true;
            if (!Array.isArray(perms) || perms.length === 0) return true;
            return perms.includes(item.can);
        };

        const sortScore = (item) => {
            const k = String(item.key ?? item.route ?? "");
            return orderIndex.has(k) ? orderIndex.get(k) : null;
        };

        const groups = menu.value
            .map((group) => {
                const items = dedupeItems(group.items ?? [])
                    .filter(canSee)
                    .slice()
                    .sort((a, b) => {
                        const sa = sortScore(a);
                        const sb = sortScore(b);

                        if (sa === null && sb === null) return 0;
                        if (sa === null) return 1;
                        if (sb === null) return -1;
                        return sa - sb;
                    });

                return { ...group, items };
            })
            .filter((g) => (g.items ?? []).length > 0);

        return groups;
    });

    return { menu, filteredMenu, props };
}
