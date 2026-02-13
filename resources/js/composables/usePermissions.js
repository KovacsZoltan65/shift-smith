import { computed, unref } from "vue";
import { usePage } from "@inertiajs/vue3";

export function usePermissions(overrideCan = null) {
    const page = usePage?.();

    const canMap = computed(() => {
        if (overrideCan) return unref(overrideCan);
        return page?.props?.auth?.can ?? {};
    });

    const has = (permission) => !!canMap.value?.[permission];

    return { has, canMap };
}
