<script setup>
import { computed, ref } from "vue";
import { Head, useForm, usePage } from "@inertiajs/vue3";
import { useToast } from "primevue/usetoast";
import CompanySelector from "@/Components/Selectors/CompanySelector.vue";

const props = defineProps({
    companies: {
        type: Array,
        default: () => [],
    },
    currentCompanyId: {
        type: Number,
        default: null,
    },
});

const toast = useToast();
const page = usePage();
const visible = ref(true);
const selectedCompanyId = ref(props.currentCompanyId ?? null);

const form = useForm({
    company_id: props.currentCompanyId ?? null,
    _token: page.props?.csrf_token ?? "",
});

const selectedCompanyName = computed(() => {
    const found = props.companies.find(
        (company) => Number(company?.id) === Number(selectedCompanyId.value),
    );

    return found?.name ?? null;
});

const submit = () => {
    if (!selectedCompanyId.value) {
        form.setError("company_id", "Válassz egy céget.");
        toast.add({
            severity: "warn",
            summary: "Figyelem",
            detail: "Cég kiválasztása kötelező.",
            life: 3000,
        });
        return;
    }

    form.clearErrors();
    form.company_id = Number(selectedCompanyId.value);

    form.post(route("company.select.store"), {
        preserveScroll: true,
        headers: {
            "X-CSRF-TOKEN": page.props?.csrf_token ?? "",
        },
        onError: (errors) => {
            const detail = Object.values(errors ?? {})
                .flat()
                .join(" ");

            toast.add({
                severity: "error",
                summary: "Hiba",
                detail: detail || "A kiválasztás mentése sikertelen.",
                life: 4000,
            });
        },
    });
};
</script>

<template>
    <Head title="Cég kiválasztása" />

    <div class="min-h-screen bg-gray-100">
        <Toast />

        <Dialog
            v-model:visible="visible"
            modal
            :draggable="false"
            :closable="false"
            :dismissableMask="false"
            header="Cég kiválasztása"
            class="w-[95vw] max-w-xl"
        >
            <div class="space-y-4">
                <p class="text-sm text-gray-700">
                    Válaszd ki, melyik cégen belül szeretnél dolgozni.
                </p>

                <CompanySelector
                    v-model="selectedCompanyId"
                    :options="companies"
                    placeholder="Cég kiválasztása"
                    class="w-full"
                    :filter="true"
                />

                <div v-if="form.errors.company_id" class="text-sm text-red-600">
                    {{ form.errors.company_id }}
                </div>

                <div class="text-sm text-gray-700">
                    Kiválasztott cég: <b>{{ selectedCompanyName ?? "-" }}</b>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Kiválaszt"
                    icon="pi pi-check"
                    :loading="form.processing"
                    @click="submit"
                />
            </template>
        </Dialog>
    </div>
</template>
