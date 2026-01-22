import { usePage } from "@inertiajs/vue3";

export function usePermissions() {
    const page = usePage();
    const can = page.props.auth?.can ?? {};
    const has = (permission) => !!can[permission];

    return { has };
}
