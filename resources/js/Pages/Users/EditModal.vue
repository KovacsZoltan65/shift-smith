<script setup>
import { computed, reactive, ref, watch } from "vue";
import Dialog from "primevue/dialog";
import Button from "primevue/button";
import UserFields from "./Partials/UserFields.vue";

const props = defineProps({
    modelValue: Boolean,
    user: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue", "saved"]);

const loading = ref(false);
const errors = reactive({});
const form = ref({ name: "", email: "" });

const hasUser = computed(() => !!props.user?.id);

const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const fill = () => {
    form.value = { name: props.user?.name ?? "", email: props.user?.email ?? "" };
    Object.keys(errors).forEach((k) => delete errors[k]);
};

watch(
    () => props.modelValue,
    (open) => open && fill()
);
watch(
    () => props.user,
    () => props.modelValue && fill()
);

const close = () => emit("update:modelValue", false);

const submit = async () => {
    if (!hasUser.value) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await fetch(`/users/${props.user.id}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf(),
                Accept: "application/json",
            },
            body: JSON.stringify(form.value),
        });

        if (res.status === 422) {
            const body = await res.json();
            const bag = body?.errors ?? {};
            for (const k of Object.keys(bag)) errors[k] = bag[k]?.[0] ?? "Hiba";
            return;
        }

        if (!res.ok) {
            const body = await res.json().catch(() => null);
            throw new Error(body?.message ?? `HTTP ${res.status}`);
        }

        emit("saved");
        close();
    } catch (e) {
        errors._global = e?.message ?? "Ismeretlen hiba";
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        header="Felhasználó szerkesztése"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div v-if="!hasUser" class="text-sm text-gray-600">
            Nincs kiválasztott felhasználó.
        </div>

        <div v-else class="space-y-4">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>
            <UserFields v-model="form" :errors="errors" :disabled="loading" />
        </div>

        <template #footer>
            <Button
                label="Mégse"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />
            <Button
                label="Mentés"
                :loading="loading"
                :disabled="!hasUser"
                @click="submit"
            />
        </template>
    </Dialog>
</template>
