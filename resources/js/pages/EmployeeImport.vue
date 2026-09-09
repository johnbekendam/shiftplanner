<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import Icon from '@/components/ui/Icon.vue'
import { FileInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const dragging = ref(false)
const busy = ref(false)
const result = ref(null)
const errors = ref([])

async function upload(file) {
    if (!file || busy.value) return

    busy.value = true
    result.value = null
    errors.value = []

    const body = new FormData()
    body.append('file', file)

    try {
        const { data } = await axios.post('/import', body)
        result.value = data
    } catch (error) {
        const payload = error?.response?.data?.errors
        errors.value = Array.isArray(payload)
            ? payload
            : payload
                ? Object.values(payload).flat()
                : [__('import.error.file')]
    } finally {
        busy.value = false
    }
}

function onDrop(event) {
    dragging.value = false
    upload(event.dataTransfer?.files?.[0] ?? null)
}

const summary = computed(() =>
    result.value
        ? __('import.result.summary', {
            created: result.value.created,
            updated: result.value.updated,
        })
        : '',
)
</script>

<template>
    <Head :title="__('import.title')" />

    <AppLayout>
        <Card class="max-w-3xl">
            <template #header>
                <div class="px-6 py-4 font-medium">{{ __('import.heading') }}</div>
            </template>

            <div class="p-6">
                <p class="mb-4 text-sm text-(--color-text-secondary)">{{ __('import.intro') }}</p>

                <FileInput accept=".csv,text/csv" @change="upload">
                    <template #default="{ trigger }">
                        <div
                            data-testid="dropzone"
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
                            <span>{{ __('import.dropzone') }}</span>
                        </div>
                    </template>
                </FileInput>

                <p v-if="busy" class="mt-4 text-sm text-(--color-text-secondary)">{{ __('import.busy') }}</p>

                <p
                    v-if="summary"
                    data-testid="import-summary"
                    class="mt-4 text-sm font-medium text-(--color-badge-success-text)"
                >{{ summary }}</p>

                <div v-if="errors.length" data-testid="import-errors" class="mt-4">
                    <p class="text-sm font-medium text-(--color-badge-error-text)">
                        {{ __('import.result.errors_heading') }}
                    </p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-(--color-badge-error-text)">
                        <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
                    </ul>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>
