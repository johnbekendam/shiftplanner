<script setup>
import { reactive, ref, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import Icon from '@/components/ui/Icon.vue'
import { TextInput, CheckboxInput } from '@/components/ui/Input'
import { useDragReorder } from '@/composables/useDragReorder'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, name, responsible, position, archived_at, shifts }. shifts is
    // read-only here (from workcenter-shift-assignments) and only used
    // to gate delete vs archive.
    items: { type: Array, default: () => [] },
    // Live-screen URL by workcenter id. Kept out of `items` so a regenerated
    // link shows at once without remounting the list and losing unsaved edits.
    liveUrls: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:items', 'regenerate-live-link'])

// Local, edit-until-Save state, seeded once from props. The parent forces
// a fresh seed by remounting this component (a :key bump) after its own
// successful save.
let nextLocalKey = -1
const rows = ref(props.items.map((item) => ({ ...item, shifts: item.shifts ?? [] })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

const { dragIndex, onDragStart, onDragOver, onDragEnd } = useDragReorder(rows)

const draft = reactive({ name: '', responsible: '' })

function add() {
    if ((draft.name ?? '').trim() === '') return

    rows.value = [
        ...rows.value,
        { id: null, _key: nextLocalKey--, name: draft.name, responsible: draft.responsible, archived_at: null, shifts: [] },
    ]
    draft.name = ''
    draft.responsible = ''
}

function remove(item) {
    rows.value = rows.value.filter((r) => r !== item)
}

function isArchived(item) {
    return item.archived_at !== null
}

function setArchived(item, archived) {
    item.archived_at = archived ? (item.archived_at ?? new Date().toISOString()) : null
}

// An unsaved row has no link yet, and an archived workcenter's link returns 404.
function liveUrlFor(item) {
    return item.id !== null && !isArchived(item) ? (props.liveUrls[item.id] ?? null) : null
}

const copiedId = ref(null)
let copiedTimer = null

async function copyLiveUrl(item) {
    try {
        await navigator.clipboard.writeText(liveUrlFor(item))
    } catch {
        // No clipboard (for example a plain-http page). The Open link stays as the fallback.
        return
    }

    copiedId.value = item.id
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => { copiedId.value = null }, 2000)
}

const regenerateId = ref(null)

function confirmRegenerate() {
    emit('regenerate-live-link', regenerateId.value)
    regenerateId.value = null
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-8 py-2" />
                    <th class="py-2 pr-3 font-medium">{{ __('workcenters.name') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('workcenters.responsible') }}</th>
                    <th class="w-64 py-2 pr-3 font-medium">{{ __('workcenters.live_screen') }}</th>
                    <th class="w-24 py-2 pr-3 font-medium" />
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="(item, index) in rows"
                    :key="item.id ?? item._key"
                    data-testid="workcenter-row"
                    class="border-b border-(--color-table-row-separator) transition-opacity"
                    :class="dragIndex === index ? 'opacity-40' : 'opacity-100'"
                    draggable="true"
                    @dragstart="onDragStart(index)"
                    @dragover.prevent="onDragOver(index)"
                    @dragend="onDragEnd"
                    @drop.prevent
                >
                    <td class="py-2 pl-1 align-middle text-(--color-text-secondary)" :aria-label="__('workcenters.drag_handle')">
                        <Icon name="bars" class="size-4 cursor-grab" />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.name"
                            class="w-full"
                            :data-testid="`workcenter-name-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="item.responsible"
                            class="w-full"
                            :data-testid="`workcenter-responsible-${item.id ?? item._key}`"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <div v-if="liveUrlFor(item)" class="flex items-center gap-1.5">
                            <a
                                :href="liveUrlFor(item)"
                                target="_blank"
                                rel="noopener"
                                class="min-w-0 flex-1 truncate py-2 text-(--color-text-link) hover:text-(--color-text-link-hover)"
                                :data-testid="`workcenter-live-link-${item.id}`"
                            >
                                {{ __('workcenters.live_open') }}
                            </a>
                            <ButtonSecondary
                                type="button"
                                :icon="copiedId === item.id ? 'check-circle' : 'link'"
                                class="px-2.5"
                                :aria-label="copiedId === item.id ? __('workcenters.live_copied') : __('workcenters.live_copy')"
                                :title="__('workcenters.live_copy')"
                                :data-testid="`workcenter-live-copy-${item.id}`"
                                @click="copyLiveUrl(item)"
                            />
                            <ButtonSecondary
                                type="button"
                                icon="arrow-path"
                                class="px-2.5"
                                :aria-label="__('workcenters.live_regenerate')"
                                :title="__('workcenters.live_regenerate')"
                                :data-testid="`workcenter-live-regenerate-${item.id}`"
                                @click="regenerateId = item.id"
                            />
                        </div>
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <CheckboxInput
                            v-if="item.shifts.length"
                            :model-value="isArchived(item)"
                            :data-testid="`workcenter-archived-${item.id ?? item._key}`"
                            @update:model-value="(v) => setArchived(item, v)"
                        >
                            {{ __('workcenters.archived') }}
                        </CheckboxInput>
                    </td>
                    <td class="px-1 py-2 align-top">
                        <ButtonDanger
                            v-if="!item.shifts.length"
                            type="button"
                            icon="bin"
                            class="w-full px-0"
                            :aria-label="__('workcenters.delete')"
                            @click="remove(item)"
                        />
                    </td>
                </tr>

                <tr v-if="!rows.length">
                    <td colspan="6" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('workcenters.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="workcenter-add-row" class="border-t border-(--color-table-row-separator)">
                    <td />
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.name"
                            class="w-full"
                            :placeholder="__('workcenters.add_name_placeholder')"
                        />
                    </td>
                    <td class="py-2 pr-3 align-top">
                        <TextInput
                            v-model="draft.responsible"
                            class="w-full"
                            :placeholder="__('workcenters.add_responsible_placeholder')"
                        />
                    </td>
                    <td />
                    <td />
                    <td class="px-1 py-2 text-right align-top">
                        <ButtonPrimary
                            type="submit"
                            icon="plus-circle"
                            class="px-2.5"
                            :aria-label="__('workcenters.add')"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <ConfirmDialog
        :open="regenerateId !== null"
        :title="__('workcenters.live_regenerate_title')"
        :confirm-label="__('workcenters.live_regenerate_confirm')"
        @confirm="confirmRegenerate"
        @cancel="regenerateId = null"
    >
        {{ __('workcenters.live_regenerate_body') }}
    </ConfirmDialog>
</template>
