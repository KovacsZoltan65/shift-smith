<script setup>
import { computed, reactive, ref, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import { trans } from "laravel-vue-i18n";

const page = usePage();

const props = defineProps({
    modelValue: Boolean,
    user: { type: Object, default: null },
});
const emit = defineEmits(["update:modelValue", "sent"]);

const loading = ref(false);
const errors = reactive({});
const hasUser = computed(() => !!props.user?.id);

//const csrf = () =>
//    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const csrf = computed(() => page.props.csrf_token ?? "");

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return;
        Object.keys(errors).forEach((k) => delete errors[k]);
    }
);

const close = () => emit("update:modelValue", false);

const send = async () => {
    if (!hasUser.value) return;

    loading.value = true;
    Object.keys(errors).forEach((k) => delete errors[k]);

    try {
        const res = await fetch(`/users/${props.user.id}/password-reset`, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "X-CSRF-TOKEN": csrf.value,
                Accept: "application/json",
            },
        });

        if (!res.ok) {
            const body = await res.json().catch(() => null);
            throw new Error(body?.message ?? `HTTP ${res.status}`);
        }

        emit("sent");
        close();
    } catch (e) {
        errors._global = e?.message ?? trans("common.unknown_error");
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        :header="trans('users.dialogs.password_reset_title')"
        :style="{ width: '520px' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div v-if="!hasUser" class="text-sm text-gray-600">
            {{ trans("users.dialogs.none_selected") }}
        </div>

        <div v-else class="space-y-3">
            <div v-if="errors._global" class="rounded border p-2 text-sm">
                {{ errors._global }}
            </div>

            <div class="text-sm text-gray-700">
                {{ trans("users.messages.password_reset_intro") }}
                <div class="mt-1 font-semibold">{{ props.user.email }}</div>
            </div>

            <div class="rounded border p-3 text-sm text-gray-600">
                {{ trans("users.messages.password_reset_notice") }}
            </div>
        </div>

        <template #footer>
            <Button
                :label="trans('common.cancel')"
                severity="secondary"
                :disabled="loading"
                @click="close"
            />
            <Button
                :label="trans('users.actions.send_email')"
                :loading="loading"
                :disabled="!hasUser"
                @click="send"
            />
        </template>
    </Dialog>
</template>
