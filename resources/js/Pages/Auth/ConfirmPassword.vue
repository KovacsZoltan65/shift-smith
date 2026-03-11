<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, useForm } from "@inertiajs/vue3";

// PrimeVue

const form = useForm({
    password: "",
});

const submit = () => {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Jelszó megerősítése" />

        <div class="mx-auto w-full max-w-md px-4 py-10">
            <Card class="shadow-sm">
                <template #title>
                    <div class="text-xl font-semibold">Jelszó megerősítése</div>
                </template>

                <template #subtitle>
                    <div class="text-sm text-gray-500">
                        Biztonsági okból kérjük, erősítsd meg a jelszavad.
                    </div>
                </template>

                <template #content>
                    <!-- Ha szeretnéd, ez lehet sima szöveg is, de a Message szépen Prime-os -->
                    <Message severity="info" :closable="false" class="mb-4">
                        Ez egy védett terület. A folytatáshoz add meg a jelszavad.
                    </Message>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <span class="p-float-label w-full">
                                <Password
                                    id="password"
                                    v-model="form.password"
                                    toggleMask
                                    :feedback="false"
                                    inputClass="w-full"
                                    class="w-full"
                                    autocomplete="current-password"
                                    :invalid="!!form.errors.password"
                                    autofocus
                                    required
                                />
                                <label for="password">Jelszó</label>
                            </span>

                            <small
                                v-if="form.errors.password"
                                class="mt-1 block text-sm text-red-600"
                            >
                                {{ form.errors.password }}
                            </small>
                        </div>

                        <Button
                            type="submit"
                            label="Megerősítés"
                            icon="pi pi-check"
                            class="w-full"
                            :loading="form.processing"
                            :disabled="form.processing"
                        />
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
