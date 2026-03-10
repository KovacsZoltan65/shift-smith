import { computed, unref } from "vue";
import { usePage } from "@inertiajs/vue3";

/**
 * Egységes olvasófelületet ad az Inertia auth.can permission maphez.
 *
 * Az overrideCan paraméter tesztben vagy speciális komponensekben lehetővé teszi,
 * hogy a jogosultságforrás felülírható maradjon a page props módosítása nélkül.
 */
export function usePermissions(overrideCan = null) {
    const page = usePage?.();

    const canMap = computed(() => {
        if (overrideCan) return unref(overrideCan);
        return page?.props?.auth?.can ?? {};
    });

    const has = (permission) => !!canMap.value?.[permission];

    return { has, canMap };
}
