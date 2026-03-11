<script setup>
import { computed } from "vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

// PrimeVue

const props = defineProps({
    status: { type: String, default: "" },
});

const form = useForm({});

const submit = () => {
    form.post(route("verification.send"));
};

const verificationLinkSent = computed(() => props.status === "verification-link-sent");
</script>

<template>
    <GuestLayout>
        <Head title="Email megerősítés" />

        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">Email megerősítés</div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        Mielőtt folytatnád, erősítsd meg az email címed.
                    </div>
                </template>

                <template #content>
                    <!-- Info message -->
                    <Message severity="info" :closable="false" class="mb-4">
                        A regisztráció után küldtünk egy megerősítő emailt. Kérjük,
                        kattints a benne található linkre.
                        <br />
                        Ha nem kaptad meg, kérhetsz újat.
                    </Message>

                    <!-- Success message -->
                    <Message
                        v-if="verificationLinkSent"
                        severity="success"
                        :closable="false"
                        class="mb-4"
                    >
                        Új megerősítő linket küldtünk az email címedre.
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
                        <Button
                            type="submit"
                            label="Megerősítő email újraküldése"
                            icon="pi pi-send"
                            class="w-full"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />

                        <Divider class="my-2" />

                        <div class="text-center">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-sm text-gray-600 hover:text-gray-900 hover:underline"
                            >
                                Kijelentkezés
                            </Link>
                        </div>
                    </form>
                </template>

                <template #footer>
                    <div class="text-center text-xs text-gray-500">
                        © {{ new Date().getFullYear() }} Shift-Smith
                    </div>
                </template>
            </Card>
        </div>
    </GuestLayout>
</template>
