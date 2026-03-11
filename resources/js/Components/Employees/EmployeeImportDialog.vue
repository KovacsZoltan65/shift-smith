<script setup>
import { computed, ref, watch } from "vue";
import { trans } from "laravel-vue-i18n";

import Dialog from "primevue/dialog";
import Button from "primevue/button";
import Select from "primevue/select";
import FileUpload from "primevue/fileupload";
import Badge from "primevue/badge";
import Message from "primevue/message";
import ProgressBar from "primevue/progressbar";

import EmployeeService from "@/services/EmployeeService.js";

const props = defineProps({
    modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "completed"]);

const formatOptions = computed(() => [
    { labelKey: "common.formats.csv", value: "csv" },
    { labelKey: "common.formats.json", value: "json" },
    { labelKey: "common.formats.xml", value: "xml" },
    { labelKey: "common.formats.xlsx", value: "xlsx" },
]);

const selectedFormat = ref("csv");
const selectedFile = ref(null);
const importing = ref(false);
const summary = ref(null);
const formErrors = ref({});
const maxFileSize = 10 * 1024 * 1024;

watch(
    () => props.modelValue,
    (visible) => {
        if (!visible) {
            selectedFormat.value = "csv";
            selectedFile.value = null;
            importing.value = false;
            formErrors.value = {};
            summary.value = null;
        }
    },
);

const fileName = computed(() => selectedFile.value?.name ?? "");
const totalSize = computed(() => selectedFile.value?.size ?? 0);
const totalSizePercent = computed(() =>
    Math.min(100, Math.round((totalSize.value / maxFileSize) * 100)),
);
const uploadMessages = computed(() => formErrors.value.file ?? []);

const close = () => {
    emit("update:modelValue", false);
};

const onFileSelect = (event) => {
    const [file] = event?.files ?? [];
    selectedFile.value = file ?? null;
    summary.value = null;
    formErrors.value = {};

    const extension = file?.name?.split(".").pop()?.toLowerCase() ?? null;
    if (["csv", "json", "xml", "xlsx"].includes(extension)) {
        selectedFormat.value = extension;
    }
};

const onFileClear = () => {
    selectedFile.value = null;
};

const onRemoveFile = (removeFileCallback, index) => {
    removeFileCallback(index);
    onFileClear();
};

const formatSize = (bytes) => {
    if (!Number.isFinite(bytes) || bytes <= 0) {
        return "0 B";
    }

    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

const runImport = async () => {
    if (!selectedFile.value) {
        formErrors.value = {
            file: [trans("employees.import.select_file")],
        };
        return;
    }

    importing.value = true;
    formErrors.value = {};

    try {
        const response = await EmployeeService.importEmployees(
            selectedFile.value,
            selectedFormat.value,
        );

        summary.value = response?.data?.data ?? null;
        emit("completed", summary.value);
    } catch (error) {
        formErrors.value = EmployeeService.extractErrors(error) ?? {};
    } finally {
        importing.value = false;
    }
};
</script>

<template>
    <Dialog
        :visible="modelValue"
        modal
        :header="$t('employees.import.dialog_title')"
        :style="{ width: '40rem' }"
        @update:visible="emit('update:modelValue', $event)"
    >
        <div class="space-y-4">
            <p class="text-sm text-slate-600">
                {{ $t("employees.import.supported_formats") }}
            </p>
            <p class="text-sm text-slate-600">
                {{ $t("employees.import.help") }}
            </p>

            <div class="grid gap-3 md:grid-cols-[12rem_minmax(0,1fr)] md:items-center">
                <label class="text-sm font-medium text-slate-700">
                    {{ $t("employees.import.supported_formats") }}
                </label>
                <Select
                    v-model="selectedFormat"
                    :options="formatOptions"
                    option-value="value"
                    data-testid="employee-import-format"
                >
                    <template #value="{ value }">
                        <span v-if="value">{{ $t(`common.formats.${value}`) }}</span>
                    </template>
                    <template #option="{ option }">
                        {{ $t(option.labelKey) }}
                    </template>
                </Select>
            </div>

            <div class="space-y-2">
                <label class="text-sm font-medium text-slate-700">
                    {{ $t("employees.import.select_file") }}
                </label>
                <FileUpload
                    name="file"
                    custom-upload
                    auto="false"
                    :multiple="false"
                    accept=".csv,.json,.xml,.xlsx"
                    :max-file-size="maxFileSize"
                    class="w-full"
                    data-testid="employee-import-uploader"
                    @select="onFileSelect"
                    @clear="onFileClear"
                >
                    <template #header="{ chooseCallback, clearCallback, files }">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex gap-2">
                                <Button
                                    icon="pi pi-file-import"
                                    rounded
                                    outlined
                                    severity="secondary"
                                    :aria-label="$t('employees.import.select_file')"
                                    data-testid="employee-import-file"
                                    @click="chooseCallback()"
                                />
                                <Button
                                    icon="pi pi-times"
                                    rounded
                                    outlined
                                    severity="danger"
                                    :disabled="!files || files.length === 0"
                                    @click="
                                        clearCallback();
                                        onFileClear();
                                    "
                                />
                            </div>
                            <ProgressBar
                                :value="totalSizePercent"
                                :show-value="false"
                                class="h-1 w-full md:ml-auto md:w-80"
                            >
                                <span class="whitespace-nowrap text-xs text-slate-600">
                                    {{ formatSize(totalSize) }} / 10 MB
                                </span>
                            </ProgressBar>
                        </div>
                    </template>

                    <template #content="{ files, removeFileCallback, messages }">
                        <div class="flex flex-col gap-4 pt-4">
                            <Message
                                v-for="messageText in [...messages, ...uploadMessages]"
                                :key="messageText"
                                severity="error"
                            >
                                {{ messageText }}
                            </Message>

                            <div v-if="files.length > 0" class="space-y-3">
                                <div
                                    v-for="(file, index) in files"
                                    :key="file.name + file.size + index"
                                    class="flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div class="min-w-0 space-y-1">
                                        <div class="truncate text-sm font-semibold text-slate-900">
                                            {{ file.name }}
                                        </div>
                                        <div class="text-xs text-slate-600">
                                            {{ formatSize(file.size) }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <Badge :value="$t('employees.import.pending')" severity="warn" />
                                        <Button
                                            icon="pi pi-times"
                                            rounded
                                            outlined
                                            severity="danger"
                                            @click="onRemoveFile(removeFileCallback, index)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template #empty>
                        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <i class="pi pi-cloud-upload mb-4 rounded-full border-2 border-slate-300 p-6 text-4xl text-slate-400" />
                            <p class="text-sm text-slate-600">
                                {{ $t("employees.import.drag_drop") }}
                            </p>
                        </div>
                    </template>
                </FileUpload>
                <p v-if="fileName" class="text-sm text-slate-600">
                    {{ $t("employees.import.selected_file", { name: fileName }) }}
                </p>
                <p v-if="formErrors.file?.length" class="text-sm text-red-600">
                    {{ formErrors.file[0] }}
                </p>
            </div>

            <div v-if="summary" class="space-y-3 rounded border border-slate-200 bg-slate-50 p-4">
                <h3 class="text-sm font-semibold text-slate-900">
                    {{ $t("employees.import.summary_title") }}
                </h3>
                <div class="grid gap-3 sm:grid-cols-4">
                    <div class="rounded bg-white p-3 text-sm shadow-sm">
                        <div class="text-slate-500">{{ $t("employees.import.total_rows") }}</div>
                        <div class="mt-1 font-semibold">{{ summary.total_rows }}</div>
                    </div>
                    <div class="rounded bg-white p-3 text-sm shadow-sm">
                        <div class="text-slate-500">{{ $t("employees.import.imported_count") }}</div>
                        <div class="mt-1 font-semibold text-emerald-700">{{ summary.imported_count }}</div>
                    </div>
                    <div class="rounded bg-white p-3 text-sm shadow-sm">
                        <div class="text-slate-500">{{ $t("employees.import.failed_count") }}</div>
                        <div class="mt-1 font-semibold text-red-700">{{ summary.failed_count }}</div>
                    </div>
                    <div class="rounded bg-white p-3 text-sm shadow-sm">
                        <div class="text-slate-500">{{ $t("employees.import.skipped_count") }}</div>
                        <div class="mt-1 font-semibold text-slate-700">{{ summary.skipped_count }}</div>
                    </div>
                </div>

                <div v-if="summary.rows?.length" class="space-y-2">
                    <h4 class="text-sm font-medium text-slate-800">
                        {{ $t("employees.import.row_results") }}
                    </h4>
                    <div class="max-h-56 space-y-2 overflow-auto">
                        <div
                            v-for="row in summary.rows"
                            :key="`${row.row_number}-${row.status}`"
                            class="rounded border border-slate-200 bg-white p-3 text-sm"
                        >
                            <div class="font-medium text-slate-900">
                                #{{ row.row_number }} - {{ row.status }}
                            </div>
                            <div class="text-slate-700">{{ row.message }}</div>
                            <ul
                                v-if="row.errors?.length"
                                class="mt-2 list-disc pl-5 text-xs text-red-700"
                            >
                                <li v-for="errorText in row.errors" :key="errorText">
                                    {{ errorText }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button
                    :label="$t('common.cancel')"
                    severity="secondary"
                    :disabled="importing"
                    @click="close"
                />
                <Button
                    :label="$t('employees.actions.import')"
                    :loading="importing"
                    :disabled="importing"
                    data-testid="employee-import-submit"
                    @click="runImport"
                />
            </div>
        </div>
    </Dialog>
</template>
