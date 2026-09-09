<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import EmailPreviewModal from '@/components/mailbox/EmailPreviewModal.vue'
import EmployeeMultiSelect from '@/components/mailbox/EmployeeMultiSelect.vue'
import { TextInput, MultilineInput, SearchInput, CheckboxInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    messages: { type: Object, default: null },
    tab:      { type: String, required: true },
    search:   { type: String, default: '' },
    counts:   { type: Object, default: () => ({}) },
    // { types: [{value,label}], type, template: {subject, body}, employees: [{id,name,email}], preselected_employee_id }
    compose:  { type: Object, default: null },
})

const tabs = ['compose', 'draft', 'outbox', 'sent']

function switchTab(tab) {
    selectedIds.value = []
    router.get('/mailbox', { tab }, { preserveState: true })
}

// ── Search (draft/outbox/sent tabs) ─────────────────────────────────────
const searchTerm = ref(props.search)

function runSearch() {
    router.get('/mailbox', { tab: props.tab, search: searchTerm.value }, { preserveState: true })
}

// ── Selection + bulk delete (draft/outbox/sent tabs) ────────────────────
const selectedIds = ref([])

function toggleSelectAll(checked) {
    selectedIds.value = checked ? props.messages.data.map(m => m.id) : []
}

function toggleSelect(id, checked) {
    selectedIds.value = checked
        ? [...selectedIds.value, id]
        : selectedIds.value.filter(existing => existing !== id)
}

function bulkDelete() {
    const message = selectedIds.value.length
        ? __('mailbox.confirm.delete_selected')
        : __('mailbox.confirm.delete_all')

    if (!confirm(message)) return

    router.post('/mailbox/bulk-delete', {
        tab:    props.tab,
        search: searchTerm.value,
        ids:    selectedIds.value,
    }, {
        onSuccess: () => { selectedIds.value = [] },
    })
}

// ── Preview modal (shared by compose preview and row-click preview) ────
const previewOpen = ref(false)
const previewLoading = ref(false)
const previewSubject = ref('')
const previewHtml = ref('')
const previewMessage = ref(null)

function openRowPreview(message) {
    previewMessage.value = message
    previewSubject.value = message.subject
    previewHtml.value = message.body_html
    previewOpen.value = true
}

// ── Compose tab ──────────────────────────────────────────────────────────
const composeForm = useForm({
    type:         props.compose?.type ?? 'personal_page_link',
    subject:      props.compose?.template?.subject ?? '',
    body:         props.compose?.template?.body ?? '',
    employee_ids: props.compose?.preselected_employee_id ? [props.compose.preselected_employee_id] : [],
})

const templateSaving = ref(false)

function changeType(type) {
    router.get('/mailbox', { tab: 'compose', type }, { preserveState: false })
}

function saveTemplate() {
    templateSaving.value = true
    router.put(`/mailbox/templates/${composeForm.type}`, {
        subject: composeForm.subject,
        body:    composeForm.body,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { templateSaving.value = false },
    })
}

async function previewCompose() {
    previewMessage.value = null
    previewLoading.value = true
    previewOpen.value = true
    const response = await fetch('/mailbox/compose/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({
            type:        composeForm.type,
            subject:     composeForm.subject,
            body:        composeForm.body,
            employee_id: composeForm.employee_ids[0] ?? null,
        }),
    })
    const data = await response.json()
    previewSubject.value = data.subject
    previewHtml.value = data.html
    previewLoading.value = false
}

function submitCompose(sendMode) {
    composeForm.transform(data => ({
        ...data,
        send_mode: sendMode,
    })).post('/mailbox/compose', {
        onSuccess: () => { composeForm.employee_ids = [] },
    })
}

// ── Row actions ──────────────────────────────────────────────────────────
function sendNow(message) {
    router.post(`/mailbox/${message.id}/send`, {}, {
        onSuccess: () => { previewOpen.value = false },
    })
}

function deleteMessage(message) {
    router.delete(`/mailbox/${message.id}`, {
        onSuccess: () => { previewOpen.value = false },
    })
}
</script>

<template>
    <AppLayout>
        <Head :title="__('nav.mailbox')" />

        <Card class="max-w-3xl">
            <template #header>
                <div class="flex w-full">
                    <button
                        v-for="t in tabs"
                        :key="t"
                        class="flex-1 px-4 py-2.5 text-sm font-medium text-center border-b-[3px] -mb-px"
                        :class="tab === t
                            ? 'border-(--color-tab-active-border) text-(--color-tab-active-text)'
                            : 'border-transparent text-(--color-tab-text) hover:text-(--color-tab-hover-text)'"
                        @click="switchTab(t)"
                    >
                        {{ __(`mailbox.tab.${t}`) }}
                        <span v-if="counts[t]" class="ml-1 opacity-60">({{ counts[t] }})</span>
                    </button>
                </div>
            </template>

            <div class="p-6">
                <!-- Compose tab -->
                <div v-if="tab === 'compose'" class="space-y-5">
                    <LabeledInput :label="__('mailbox.compose.type')">
                        <SelectInput
                            :model-value="composeForm.type"
                            :options="compose?.types ?? []"
                            class="w-full"
                            @update:model-value="changeType"
                        />
                    </LabeledInput>

                    <LabeledInput :label="__('mailbox.compose.subject')" :error="composeForm.errors.subject">
                        <TextInput v-model="composeForm.subject" class="w-full" />
                    </LabeledInput>

                    <LabeledInput :label="__('mailbox.compose.body')" :error="composeForm.errors.body">
                        <MultilineInput v-model="composeForm.body" rows="10" class="w-full" />
                        <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('mailbox.compose.body_hint') }}</p>
                    </LabeledInput>

                    <div class="flex justify-end">
                        <ButtonSecondary type="button" icon="check" :disabled="templateSaving" @click="saveTemplate">
                            {{ __('mailbox.compose.template_save') }}
                        </ButtonSecondary>
                    </div>

                    <LabeledInput :label="__('mailbox.compose.employees')" :error="composeForm.errors.employee_ids">
                        <EmployeeMultiSelect
                            v-model="composeForm.employee_ids"
                            :employees="compose?.employees ?? []"
                        />
                        <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('mailbox.compose.employees_hint') }}</p>
                    </LabeledInput>

                    <div class="flex justify-end gap-3">
                        <ButtonSecondary type="button" icon="eye" @click="previewCompose">
                            {{ __('mailbox.compose.preview') }}
                        </ButtonSecondary>
                        <ButtonSecondary type="button" :disabled="composeForm.processing || !composeForm.employee_ids.length" @click="submitCompose('draft')">
                            {{ __('mailbox.compose.create_drafts') }}
                        </ButtonSecondary>
                        <ButtonPrimary type="button" :disabled="composeForm.processing || !composeForm.employee_ids.length" @click="submitCompose('queue')">
                            {{ __('mailbox.action.send_now') }}
                        </ButtonPrimary>
                    </div>
                </div>

                <!-- Draft / outbox / sent tabs -->
                <div v-else class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <SearchInput v-model="searchTerm" class="max-w-xs" @keyup.enter="runSearch" />

                        <ButtonSecondary type="button" icon="bin" @click="bulkDelete">
                            {{ selectedIds.length ? __('mailbox.action.delete') : __('mailbox.action.delete_all') }}
                            <span
                                v-if="selectedIds.length"
                                class="ml-1.5 inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-xs bg-[var(--color-badge-standard-bg)] text-[var(--color-badge-standard-text)]"
                            >
                                {{ selectedIds.length }}
                            </span>
                        </ButtonSecondary>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                                <th class="w-8 py-2">
                                    <CheckboxInput
                                        :model-value="messages.data.length > 0 && selectedIds.length === messages.data.length"
                                        @update:model-value="toggleSelectAll"
                                    />
                                </th>
                                <th class="py-2">{{ __('mailbox.column.recipient') }}</th>
                                <th class="py-2">{{ __('mailbox.column.subject') }}</th>
                                <th class="py-2">{{ __('mailbox.column.composed_by') }}</th>
                                <th class="py-2">{{ __('mailbox.column.status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="message in messages.data"
                                :key="message.id"
                                class="cursor-pointer border-b border-(--color-table-row-separator) hover:bg-(--color-table-row-hover-bg)"
                                @click="openRowPreview(message)"
                            >
                                <td class="py-2" @click.stop>
                                    <CheckboxInput
                                        :model-value="selectedIds.includes(message.id)"
                                        @update:model-value="checked => toggleSelect(message.id, checked)"
                                    />
                                </td>
                                <td class="py-2 text-(--color-table-row-text)">{{ message.recipient_email }}</td>
                                <td class="py-2 text-(--color-table-row-text)">{{ message.subject }}</td>
                                <td class="py-2 text-(--color-text-secondary)">{{ message.composed_by }}</td>
                                <td class="py-2 text-(--color-text-secondary)">{{ message.status }}</td>
                            </tr>
                            <tr v-if="!messages.data.length">
                                <td colspan="5" class="py-8 text-center text-(--color-text-secondary)">
                                    {{ __('mailbox.empty') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Card>

        <EmailPreviewModal
            v-if="previewOpen"
            :subject="previewSubject"
            :html="previewHtml"
            :loading="previewLoading"
            @close="previewOpen = false"
        >
            <template v-if="previewMessage" #actions>
                <ButtonSecondary
                    v-if="previewMessage.status === 'draft'"
                    type="button"
                    icon="paper-airplane"
                    @click="sendNow(previewMessage)"
                >
                    {{ __('mailbox.action.send_now') }}
                </ButtonSecondary>
                <ButtonSecondary type="button" icon="bin" @click="deleteMessage(previewMessage)">
                    {{ __('mailbox.action.delete') }}
                </ButtonSecondary>
            </template>
        </EmailPreviewModal>
    </AppLayout>
</template>
