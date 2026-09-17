<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import Card from '@/components/ui/Card.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import Icon from '@/components/ui/Icon.vue'
import { FileInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()
const dragging = ref(false)
const busy = ref(false)
const result = ref(null)
const errors = ref([])
const pendingFile = ref(null)
const confirmOpen = ref(false)

async function upload(file) {
    if (!file || busy.value) return

    busy.value = true
    result.value = null
    errors.value = []

    const body = new FormData()
    body.append('file', file)

    try {
        const { data } = await axios.post('/employee-backup/import', body)
        result.value = data
    } catch (error) {
        const payload = error?.response?.data?.errors
        errors.value = Array.isArray(payload)
            ? payload
            : payload
                ? Object.values(payload).flat()
                : [__('backup.error.file')]
    } finally {
        busy.value = false
    }
}

function requestImport(file) {
    if (!file || busy.value) return

    pendingFile.value = file
    confirmOpen.value = true
}

function cancelImport() {
    pendingFile.value = null
    confirmOpen.value = false
}

function confirmImport() {
    const file = pendingFile.value
    pendingFile.value = null
    confirmOpen.value = false
    upload(file)
}

function onDrop(event) {
    dragging.value = false
    requestImport(event.dataTransfer?.files?.[0] ?? null)
}

const summary = computed(() => {
    if (!result.value) return ''

    return result.value.version === 2
        ? __('backup.result.imported', result.value)
        : __('backup.result.summary', result.value)
})
</script>

<template>
    <Head :title="__('backup.title')" />

    <AppLayout>
        <Card class="max-w-3xl">
            <template #header>
                <div class="px-6 py-4 font-medium">{{ __('backup.heading') }}</div>
            </template>

            <div class="space-y-6 p-6">
                <form action="/employee-backup/export" method="get">
                    <ButtonPrimary data-testid="backup-export" type="submit" icon="download">
                        {{ __('backup.export') }}
                    </ButtonPrimary>
                </form>

                <FileInput accept="application/json,.json" @change="requestImport">
                    <template #default="{ trigger }">
                        <div
                            data-testid="backup-dropzone"
                            role="button"
                            tabindex="0"
                            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-12 text-center text-sm text-(--color-text-secondary) border-(--color-input-border) hover:border-(--color-input-focus-border)"
                            :class="dragging ? 'border-(--color-input-focus-border) bg-(--color-surface-secondary-bg)' : ''"
                            @click="trigger"
                            @keydown.enter.prevent="trigger"
                            @keydown.space.prevent="trigger"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop"
                        >
                            <Icon name="upload" class="size-8" />
                            <span>{{ __('backup.dropzone') }}</span>
                        </div>
                    </template>
                </FileInput>

                <ConfirmDialog
                    :open="confirmOpen"
                    :title="__('backup.confirm.title')"
                    :confirm-label="__('backup.confirm.action')"
                    @confirm="confirmImport"
                    @cancel="cancelImport"
                >
                    {{ __('backup.confirm.message') }}
                </ConfirmDialog>

                <p v-if="busy" class="text-sm text-(--color-text-secondary)">{{ __('backup.busy') }}</p>

                <p v-if="summary" data-testid="backup-summary" class="text-sm font-medium text-(--color-badge-success-text)">
                    {{ summary }}
                </p>

                <div v-if="errors.length" data-testid="backup-errors">
                    <p class="text-sm font-medium text-(--color-badge-error-text)">
                        {{ __('backup.result.errors_heading') }}
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-(--color-badge-error-text)">
                        <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>