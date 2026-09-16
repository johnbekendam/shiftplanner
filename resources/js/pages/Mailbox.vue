<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import EmailPreviewModal from '@/components/mailbox/EmailPreviewModal.vue'
import RecipientPicker from '@/components/mailbox/RecipientPicker.vue'
import UnresolvedRecipientsDialog from '@/components/mailbox/UnresolvedRecipientsDialog.vue'
import { TextInput, MultilineInput, SearchInput, CheckboxInput, SelectInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    messages: { type: Object, default: null },
    tab:      { type: String, required: true },
    search:   { type: String, default: '' },
    counts:   { type: Object, default: () => ({}) },
    // { types: [{value,label}], type, template: {subject, body}, employees: [{id,name,email}],
    //   users: [{id,name,email}], preselected_employee_id, unresolved_recipients: [{name,email,tokens}]|null,
    //   placeholder_tokens: string[] }
    compose:  { type: Object, default: null },
})

const tabs = ['compose', 'draft', 'outbox', 'sent']

function switchTab(tab) {
    selectedIds.value = []
    // Compose seeds a useForm from props at setup time, so it needs a fresh
    // mount — preserving state would leave the form empty (bug: empty compose
    // on tab switch, fills in only after a reload).
    router.get('/mailbox', { tab }, { preserveState: tab !== 'compose' })
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
    type:         props.compose?.type ?? 'custom',
    subject:      props.compose?.template?.subject ?? '',
    body:         props.compose?.template?.body ?? '',
    employee_ids: props.compose?.preselected_employee_id ? [props.compose.preselected_employee_id] : [],
    user_ids:     [],
})

const recipients = computed({
    get: () => ({ employee_ids: composeForm.employee_ids, user_ids: composeForm.user_ids }),
    set: (value) => {
        composeForm.employee_ids = value.employee_ids
        composeForm.user_ids = value.user_ids
    },
})

const hasRecipients = computed(() => composeForm.employee_ids.length || composeForm.user_ids.length)
const recipientPickerOpen = ref(false)

function insertPlaceholder(token) {
    const needsSpace = composeForm.body.length > 0 && !composeForm.body.endsWith(' ') && !composeForm.body.endsWith('\n')
    composeForm.body += (needsSpace ? ' ' : '') + token
}

// A :button[label](url) markdown snippet — MarkdownRenderer turns it into a
// styled button in the rendered email. "label" and "url" are edited by hand
// after inserting, same as filling in any other snippet placeholder text.
function insertButtonSnippet() {
    const needsNewline = composeForm.body.length > 0 && !composeForm.body.endsWith('\n')
    composeForm.body += (needsNewline ? '\n\n' : '') + ':button[label](url)'
}

const templateSaving = ref(false)

// Tracks the last saved subject/body so the Save button can highlight once
// the admin has actually edited the template. Type changes remount the
// component (see changeType), so this never needs resetting mid-session.
const savedSubject = ref(composeForm.subject)
const savedBody = ref(composeForm.body)
const templateChanged = computed(() => composeForm.subject !== savedSubject.value || composeForm.body !== savedBody.value)

// ── Unresolved-placeholder confirmation (compose tab) ───────────────────
const unresolvedRecipients = ref(props.compose?.unresolved_recipients ?? null)

function changeType(type) {
    router.get('/mailbox', { tab: 'compose', type }, { preserveState: false })
}

function cancelTemplateEdit() {
    composeForm.subject = savedSubject.value
    composeForm.body = savedBody.value
}

function saveTemplate() {
    templateSaving.value = true
    router.put(`/mailbox/templates/${composeForm.type}`, {
        subject: composeForm.subject,
        body:    composeForm.body,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            savedSubject.value = composeForm.subject
            savedBody.value = composeForm.body
        },
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
            user_id:     composeForm.employee_ids.length ? null : (composeForm.user_ids[0] ?? null),
        }),
    })
    const data = await response.json()
    previewSubject.value = data.subject
    previewHtml.value = data.html
    previewLoading.value = false
}

const pendingSendMode = ref('draft')

function submitCompose(sendMode, excludeUnresolved = false) {
    pendingSendMode.value = sendMode
    composeForm.transform(data => ({
        ...data,
        send_mode:          sendMode,
        exclude_unresolved: excludeUnresolved,
    })).post('/mailbox/compose', {
        preserveState: true,
        onSuccess: () => {
            unresolvedRecipients.value = props.compose?.unresolved_recipients ?? null
            if (!unresolvedRecipients.value) {
                composeForm.employee_ids = []
                composeForm.user_ids = []
            }
        },
    })
}

function continueWithoutUnresolved() {
    submitCompose(pendingSendMode.value, true)
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
                    <div class="flex items-end gap-3">
                        <LabeledInput :label="__('mailbox.compose.type')" class="flex-1">
                            <SelectInput
                                :model-value="composeForm.type"
                                :options="compose?.types ?? []"
                                class="w-full"
                                @update:model-value="changeType"
                            />
                        </LabeledInput>

                        <ButtonSecondary
                            v-if="templateChanged"
                            type="button"
                            class="h-9 !py-0 !px-3 text-xs"
                            :disabled="templateSaving"
                            @click="cancelTemplateEdit"
                        >
                            {{ __('mailbox.compose.template_cancel') }}
                        </ButtonSecondary>

                        <ButtonPrimary
                            v-if="templateChanged"
                            type="button"
                            class="h-9 !py-0 !px-3 text-xs"
                            :disabled="templateSaving"
                            @click="saveTemplate"
                        >
                            {{ __('mailbox.compose.template_save') }}
                        </ButtonPrimary>
                    </div>

                    <RecipientPicker
                        v-model="recipients"
                        v-model:open="recipientPickerOpen"
                        :employees="compose?.employees ?? []"
                        :users="compose?.users ?? []"
                        :error="composeForm.errors.employee_ids"
                    />

                    <LabeledInput :label="__('mailbox.compose.subject')" :error="composeForm.errors.subject">
                        <TextInput v-model="composeForm.subject" live class="w-full" />
                    </LabeledInput>

                    <LabeledInput :label="__('mailbox.compose.body')" :error="composeForm.errors.body">
                        <MultilineInput v-model="composeForm.body" rows="10" class="w-full" />
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-(--color-text-secondary)">{{ __('mailbox.compose.insert_placeholder') }}</span>
                            <button
                                v-for="token in compose?.placeholder_tokens ?? []"
                                :key="token"
                                type="button"
                                class="rounded-full px-2 py-0.5 text-xs bg-(--color-badge-standard-bg) text-(--color-badge-standard-text) hover:opacity-80"
                                @click="insertPlaceholder(token)"
                            >
                                {{ token }}
                            </button>
                            <button
                                type="button"
                                class="rounded-full px-2 py-0.5 text-xs bg-(--color-badge-standard-bg) text-(--color-badge-standard-text) hover:opacity-80"
                                @click="insertButtonSnippet"
                            >
                                {{ __('mailbox.compose.insert_button') }}
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-(--color-text-secondary)">{{ __('mailbox.compose.body_hint') }}</p>
                    </LabeledInput>

                    <CardSeparator />

                    <div class="flex justify-end gap-3">
                        <ButtonSecondary type="button" icon="eye" @click="previewCompose">
                            {{ __('mailbox.compose.preview') }}
                        </ButtonSecondary>
                        <ButtonSecondary type="button" :disabled="composeForm.processing || !hasRecipients" @click="submitCompose('draft')">
                            {{ __('mailbox.compose.create_drafts') }}
                        </ButtonSecondary>
                        <ButtonPrimary type="button" :disabled="composeForm.processing || !hasRecipients" @click="submitCompose('queue')">
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

        <UnresolvedRecipientsDialog
            v-if="unresolvedRecipients"
            :recipients="unresolvedRecipients"
            @cancel="unresolvedRecipients = null"
            @continue="continueWithoutUnresolved"
        />
    </AppLayout>
</template>
