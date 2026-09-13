<script setup>
import { reactive, ref, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import Icon from '@/components/ui/Icon.vue'
import { TextInput, CheckboxInput, NumberInput } from '@/components/ui/Input'
import { useDragReorder } from '@/composables/useDragReorder'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const WEEKDAYS = [
    'workcenters.shifts.weekday.mon',
    'workcenters.shifts.weekday.tue',
    'workcenters.shifts.weekday.wed',
    'workcenters.shifts.weekday.thu',
    'workcenters.shifts.weekday.fri',
    'workcenters.shifts.weekday.sat',
    'workcenters.shifts.weekday.sun',
]

const props = defineProps({
    // Rows of { id, name, description, position, archived_at, shifts }.
    // Each shifts entry is { id, name, weekday_capacities: number[7] }.
    items: { type: Array, default: () => [] },
    // Every shift available to attach: { id, name, start_time, end_time }.
    allShifts: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:items'])

// Local, edit-until-Save state, seeded once from props. The parent forces
// a fresh seed by remounting this component (a :key bump) after its own
// successful save.
let nextLocalKey = -1
const rows = ref(props.items.map((item) => ({ ...item, shifts: (item.shifts ?? []).map((s) => ({ ...s })) })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

const { dragIndex, onDragStart, onDragOver, onDragEnd } = useDragReorder(rows)

const draft = reactive({ name: '', description: '' })

function add() {
    if ((draft.name ?? '').trim() === '') return

    rows.value = [
        ...rows.value,
        {
            id: null,
            _key: nextLocalKey--,
            name: draft.name,
            description: draft.description,
            archived_at: null,
            shifts: [],
        },
    ]
    draft.name = ''
    draft.description = ''
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

const expandedIds = ref(new Set())

function toggleExpanded(item) {
    const next = new Set(expandedIds.value)
    next.has(item.id) ? next.delete(item.id) : next.add(item.id)
    expandedIds.value = next
}

function isShiftAttached(item, shiftId) {
    return item.shifts.some((s) => s.id === shiftId)
}

function toggleShift(item, shift, attach) {
    if (attach) {
        item.shifts = [...item.shifts, { id: shift.id, name: shift.name, weekday_capacities: [0, 0, 0, 0, 0, 0, 0] }]
    } else {
        item.shifts = item.shifts.filter((s) => s.id !== shift.id)
    }
}
</script>

<template>
    <form @submit.prevent="add">
        <table class="w-full table-fixed text-sm">
            <thead>
                <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                    <th class="w-8 py-2" />
                    <th class="w-8 py-2" />
                    <th class="py-2 pr-3 font-medium">{{ __('workcenters.name') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('workcenters.description') }}</th>
                    <th class="w-24 py-2 pr-3 font-medium" />
                    <th class="w-14 py-2" />
                </tr>
            </thead>
            <tbody>
                <template v-for="(item, index) in rows" :key="item.id ?? item._key">
                    <tr
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
                        <td class="py-2 align-top">
                            <button
                                v-if="item.id !== null"
                                type="button"
                                class="text-(--color-text-secondary)"
                                :data-testid="`workcenter-expand-${item.id}`"
                                :aria-label="__('workcenters.shifts.expand')"
                                @click="toggleExpanded(item)"
                            >
                                <Icon :name="expandedIds.has(item.id) ? 'chevron-down' : 'chevron-right'" class="size-4" />
                            </button>
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
                                v-model="item.description"
                                class="w-full"
                                :data-testid="`workcenter-description-${item.id ?? item._key}`"
                            />
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

                    <tr v-if="item.id !== null && expandedIds.has(item.id)" :data-testid="`workcenter-detail-${item.id}`">
                        <td />
                        <td colspan="5" class="space-y-4 pb-4">
                            <div>
                                <p class="mb-2 font-medium text-(--color-text-secondary)">{{ __('workcenters.shifts.attach') }}</p>
                                <div class="flex flex-wrap gap-x-4 gap-y-2">
                                    <CheckboxInput
                                        v-for="shift in allShifts"
                                        :key="shift.id"
                                        :model-value="isShiftAttached(item, shift.id)"
                                        :data-testid="`workcenter-shift-${item.id}-${shift.id}`"
                                        @update:model-value="(v) => toggleShift(item, shift, v)"
                                    >
                                        {{ shift.name }} ({{ shift.start_time }}–{{ shift.end_time }})
                                    </CheckboxInput>
                                </div>
                            </div>

                            <div v-for="shift in item.shifts" :key="shift.id" class="space-y-1">
                                <p class="font-medium text-(--color-text-secondary)">{{ shift.name }}</p>
                                <div class="grid grid-cols-7 gap-2">
                                    <div v-for="(label, weekday) in WEEKDAYS" :key="weekday">
                                        <span class="block text-xs text-(--color-text-secondary)">{{ __(label) }}</span>
                                        <NumberInput
                                            v-model="shift.weekday_capacities[weekday]"
                                            :min="0"
                                            class="w-full"
                                            :data-testid="`workcenter-capacity-${item.id}-${shift.id}-${weekday}`"
                                        />
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>

                <tr v-if="!rows.length">
                    <td colspan="6" class="py-6 text-center text-(--color-text-secondary)">
                        {{ __('workcenters.list_empty') }}
                    </td>
                </tr>

                <tr data-testid="workcenter-add-row" class="border-t border-(--color-table-row-separator)">
                    <td />
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
                            v-model="draft.description"
                            class="w-full"
                            :placeholder="__('workcenters.add_description_placeholder')"
                        />
                    </td>
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
</template>
