<script setup>
import { reactive, ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import TabSaveBar from '@/components/ui/TabSaveBar.vue'
import { SelectInput, NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { useUnsavedChangesGuard } from '@/composables/useUnsavedChangesGuard'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    workcenters: { type: Array, default: () => [] }, // { id, name }, active only
    shifts: { type: Array, default: () => [] }, // { id, name, start_time, end_time }
    assignments: { type: Array, default: () => [] }, // { workcenter_id, shift_id, spots: number[7] }
})

const WEEKDAYS = [
    'workcenter_shifts.weekday.mon',
    'workcenter_shifts.weekday.tue',
    'workcenter_shifts.weekday.wed',
    'workcenter_shifts.weekday.thu',
    'workcenter_shifts.weekday.fri',
    'workcenter_shifts.weekday.sat',
    'workcenter_shifts.weekday.sun',
]

const workcenterName = (id) => props.workcenters.find((w) => w.id === id)?.name ?? `#${id}`
const shiftName = (id) => props.shifts.find((s) => s.id === id)?.name ?? `#${id}`

const workcenterOptions = computed(() => props.workcenters.map((w) => ({ value: w.id, label: w.name })))
const shiftOptions = computed(() => props.shifts.map((s) => ({
    value: s.id,
    label: `${s.name} (${s.start_time}–${s.end_time})`,
})))

function key(row) {
    return `${row.workcenter_id}:${row.shift_id}`
}

function seed() {
    return props.assignments.map((a) => ({ workcenter_id: a.workcenter_id, shift_id: a.shift_id, spots: [...a.spots] }))
}

const committed = ref(seed())
const rows = ref(seed())
const saving = ref(false)
const justSaved = ref(false)

const draft = reactive({ workcenter_id: null, shift_id: null, spots: [0, 0, 0, 0, 0, 0, 0] })

function add() {
    if (draft.workcenter_id === null || draft.shift_id === null) return

    rows.value = [
        ...rows.value,
        { workcenter_id: draft.workcenter_id, shift_id: draft.shift_id, spots: [...draft.spots] },
    ]
    draft.workcenter_id = null
    draft.shift_id = null
    draft.spots = [0, 0, 0, 0, 0, 0, 0]
}

function remove(row) {
    rows.value = rows.value.filter((r) => r !== row)
}

const dirty = computed(() => {
    const committedKeys = committed.value.map(key)
    const currentKeys = rows.value.map(key)

    if (currentKeys.some((k) => !committedKeys.includes(k))) return true
    if (committedKeys.some((k) => !currentKeys.includes(k))) return true

    return rows.value.some((row) => {
        const orig = committed.value.find((c) => key(c) === key(row))
        return orig && JSON.stringify(orig.spots) !== JSON.stringify(row.spots)
    })
})

async function save() {
    saving.value = true
    const committedKeys = committed.value.map(key)
    const currentKeys = rows.value.map(key)

    const toDelete = committed.value.filter((c) => !currentKeys.includes(key(c)))
    const toAdd = rows.value.filter((r) => !committedKeys.includes(key(r)))
    const toEdit = rows.value.filter((r) => {
        if (!committedKeys.includes(key(r))) return false
        const orig = committed.value.find((c) => key(c) === key(r))
        return orig && JSON.stringify(orig.spots) !== JSON.stringify(r.spots)
    })

    const results = await Promise.allSettled([
        ...toDelete.map((r) => deleteAsync(`/schedule/${r.workcenter_id}/${r.shift_id}`)),
        ...toEdit.map((r) => putAsync(`/schedule/${r.workcenter_id}/${r.shift_id}`, { spots: r.spots })),
        ...toAdd.map((r) => postAsync('/schedule', {
            workcenter_id: r.workcenter_id,
            shift_id: r.shift_id,
            spots: r.spots,
        })),
    ])

    saving.value = false
    const ok = results.every((r) => r.status === 'fulfilled')
    if (ok) {
        committed.value = seed()
        rows.value = seed()
        justSaved.value = true
        setTimeout(() => { justSaved.value = false }, 2000)
    }
    return ok
}

function cancel() {
    rows.value = committed.value.map((r) => ({ ...r, spots: [...r.spots] }))
}

useUnsavedChangesGuard(() => dirty.value)
</script>

<template>
    <AppLayout>
        <Head :title="__('workcenter_shifts.title')" />

        <Card class="max-w-4xl">
            <div class="p-6">
                <table class="w-full table-fixed text-sm">
                    <thead>
                        <tr class="border-b border-(--color-table-header-separator) text-left text-(--color-table-header-text)">
                            <th class="py-2 pr-3 font-medium">{{ __('workcenter_shifts.column.workcenter') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('workcenter_shifts.column.shift') }}</th>
                            <th v-for="label in WEEKDAYS" :key="label" class="w-14 py-2 pr-2 font-medium">
                                {{ __(label) }}
                            </th>
                            <th class="w-14 py-2" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="key(row)"
                            data-testid="workcenter-shift-row"
                            class="border-b border-(--color-table-row-separator)"
                        >
                            <td class="py-2 pr-3 align-middle">{{ workcenterName(row.workcenter_id) }}</td>
                            <td class="py-2 pr-3 align-middle">{{ shiftName(row.shift_id) }}</td>
                            <td v-for="(label, weekday) in WEEKDAYS" :key="label" class="py-2 pr-2 align-middle">
                                <NumberInput
                                    v-model="row.spots[weekday]"
                                    :min="0"
                                    class="w-full"
                                    :data-testid="`workcenter-shift-spots-${row.workcenter_id}-${row.shift_id}-${weekday}`"
                                />
                            </td>
                            <td class="px-1 py-2 align-middle">
                                <ButtonDanger
                                    type="button"
                                    icon="bin"
                                    class="w-full px-0"
                                    :aria-label="__('workcenter_shifts.delete')"
                                    @click="remove(row)"
                                />
                            </td>
                        </tr>

                        <tr v-if="!rows.length">
                            <td :colspan="10" class="py-6 text-center text-(--color-text-secondary)">
                                {{ __('workcenter_shifts.list_empty') }}
                            </td>
                        </tr>

                        <tr data-testid="workcenter-shift-add-row" class="border-t border-(--color-table-row-separator)">
                            <td class="py-2 pr-3 align-top">
                                <SelectInput
                                    v-model="draft.workcenter_id"
                                    :options="workcenterOptions"
                                    :placeholder="__('workcenter_shifts.select_workcenter')"
                                    class="w-full"
                                />
                            </td>
                            <td class="py-2 pr-3 align-top">
                                <SelectInput
                                    v-model="draft.shift_id"
                                    :options="shiftOptions"
                                    :placeholder="__('workcenter_shifts.select_shift')"
                                    class="w-full"
                                />
                            </td>
                            <td v-for="(label, weekday) in WEEKDAYS" :key="label" class="py-2 pr-2 align-top">
                                <NumberInput v-model="draft.spots[weekday]" :min="0" class="w-full" />
                            </td>
                            <td class="px-1 py-2 text-right align-top">
                                <ButtonPrimary
                                    type="button"
                                    icon="plus-circle"
                                    class="px-2.5"
                                    :aria-label="__('workcenter_shifts.add')"
                                    @click="add"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <TabSaveBar :dirty="dirty" :saving="saving" :just-saved="justSaved" @save="save" @cancel="cancel" />
            </div>
        </Card>
    </AppLayout>
</template>
