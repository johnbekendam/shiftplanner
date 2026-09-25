<script setup>
import { reactive, ref, computed, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import { SelectInput, NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, type, mode, severity, config }.
    items: { type: Array, default: () => [] },
    workcenters: { type: Array, default: () => [] }, // { id, name }
    competences: { type: Array, default: () => [] }, // { id, name }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
    shifts: { type: Array, default: () => [] }, // { id, name }
})

const emit = defineEmits(['update:items'])

const SINGLETON_TYPES = ['max_hours_per_week', 'max_shifts_per_day', 'not_preferred_shift', 'equal_workload']
// Types whose mode the manager cannot choose: no mode at all, or always hard.
const FIXED_MODE_TYPES = ['equal_workload', 'alternating_shift_pair']
const ALL_TYPES = [...SINGLETON_TYPES, 'competence_required', 'business_line_preference', 'alternating_shift_pair']

const typeOptions = computed(() => ALL_TYPES.map((t) => ({ value: t, label: __(`planning_rules.type.${t}`) })))
const modeOptions = computed(() => [
    { value: 'hard', label: __('planning_rules.mode.hard') },
    { value: 'soft', label: __('planning_rules.mode.soft') },
])
const workcenterOptions = computed(() => props.workcenters.map((w) => ({ value: w.id, label: w.name })))
const competenceOptions = computed(() => props.competences.map((c) => ({ value: c.id, label: c.name })))
const businessLineOptions = computed(() => props.businessLines.map((bl) => ({ value: bl.id, label: bl.abbreviation })))
const shiftOptions = computed(() => props.shifts.map((s) => ({ value: s.id, label: s.name })))
const secondShiftOptions = computed(() => shiftOptions.value.filter((option) => option.value !== draft.first_shift_id))

const workcenterName = (id) => props.workcenters.find((w) => w.id === id)?.name ?? `#${id}`
const competenceName = (id) => props.competences.find((c) => c.id === id)?.name ?? `#${id}`
const shiftName = (id) => props.shifts.find((s) => s.id === id)?.name ?? `#${id}`

// Local, edit-until-Save state, seeded once from props. The parent forces a
// fresh seed by remounting this component (a :key bump) after its own
// successful save, matching BusinessLineList and friends.
let nextLocalKey = -1
const rows = ref(props.items.map((r) => ({ ...r, config: { ...r.config } })))

watch(rows, () => emit('update:items', rows.value), { deep: true })

// A singleton type already present cannot be added again.
const availableTypeOptions = computed(() => typeOptions.value.filter((opt) => {
    if (!SINGLETON_TYPES.includes(opt.value)) return true

    return !rows.value.some((r) => r.type === opt.value)
}))

function freshDraft() {
    return {
        type: '',
        mode: 'hard',
        severity: null,
        value: 1,
        workcenter_id: null,
        competence_id: null,
        business_line_id: null,
        first_shift_id: null,
        second_shift_id: null,
    }
}

const draft = reactive(freshDraft())

function configFor(type, source) {
    switch (type) {
        case 'max_shifts_per_day':
            return { value: source.value }
        case 'competence_required':
            return { workcenter_id: source.workcenter_id, competence_id: source.competence_id }
        case 'business_line_preference':
            return { workcenter_id: source.workcenter_id, business_line_id: source.business_line_id }
        case 'alternating_shift_pair':
            return { first_shift_id: source.first_shift_id, second_shift_id: source.second_shift_id }
        default:
            return {}
    }
}

function canAdd() {
    if (!draft.type) return false
    if (draft.type === 'alternating_shift_pair' && (!draft.first_shift_id || !draft.second_shift_id)) return false
    if (!FIXED_MODE_TYPES.includes(draft.type) && draft.mode === 'soft' && !draft.severity) return false
    if (draft.type === 'max_shifts_per_day' && !draft.value) return false
    if (draft.type === 'competence_required' && (!draft.workcenter_id || !draft.competence_id)) return false
    if (draft.type === 'business_line_preference' && (!draft.workcenter_id || !draft.business_line_id)) return false

    return true
}

function add() {
    if (!canAdd()) return

    rows.value = [
        ...rows.value,
        {
            id: null,
            _key: nextLocalKey--,
            type: draft.type,
            mode: draft.type === 'equal_workload' ? null : (draft.type === 'alternating_shift_pair' ? 'hard' : draft.mode),
            severity: FIXED_MODE_TYPES.includes(draft.type) || draft.mode !== 'soft' ? null : draft.severity,
            config: configFor(draft.type, draft),
        },
    ]
    Object.assign(draft, freshDraft())
}

function remove(row) {
    rows.value = rows.value.filter((r) => r !== row)
}
</script>

<template>
    <div class="space-y-4">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[42rem] text-left text-sm">
                <thead class="border-b border-(--color-border) text-xs font-semibold uppercase tracking-wide text-(--color-text-secondary)">
                    <tr>
                        <th scope="col" class="px-3 py-2">{{ __('planning_rules.type') }}</th>
                        <th scope="col" class="w-px whitespace-nowrap px-3 py-2">{{ __('planning_rules.mode') }}</th>
                        <th scope="col" class="w-px whitespace-nowrap px-3 py-2">{{ __('planning_rules.severity_column') }}</th>
                        <th scope="col" class="w-px whitespace-nowrap px-3 py-2"><span class="sr-only">{{ __('planning_rules.delete') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-(--color-border)">
                    <tr v-for="row in rows" :key="row.id ?? row._key" data-testid="planning-rule-row">
                        <td class="px-3 py-3 align-top">
                            <p class="font-medium text-(--color-text-primary)">{{ __(`planning_rules.type.${row.type}`) }}</p>
                            <p v-if="row.type === 'competence_required'" class="mt-1 text-(--color-text-secondary)">
                                {{ workcenterName(row.config.workcenter_id) }} → {{ competenceName(row.config.competence_id) }}
                            </p>
                            <div v-else-if="row.type === 'business_line_preference'" class="mt-2 space-y-2">
                                <p class="text-(--color-text-secondary)">{{ workcenterName(row.config.workcenter_id) }}</p>
                                <SelectInput v-model="row.config.business_line_id" :options="businessLineOptions" class="w-full max-w-xs" />
                            </div>
                            <p v-else-if="row.type === 'max_shifts_per_day'" class="mt-2">
                                <NumberInput v-model="row.config.value" :min="1" class="w-32" />
                            </p>
                            <p v-else-if="row.type === 'max_hours_per_week'" class="mt-1 text-(--color-text-secondary)">
                                {{ __('planning_rules.max_hours_per_week_hint') }}
                            </p>
                            <p v-else-if="row.type === 'not_preferred_shift'" class="mt-1 text-(--color-text-secondary)">
                                {{ __('planning_rules.not_preferred_shift_hint') }}
                            </p>
                            <p v-else-if="row.type === 'equal_workload'" class="mt-1 text-(--color-text-secondary)">
                                {{ __('planning_rules.equal_workload_hint') }}
                            </p>
                            <template v-else-if="row.type === 'alternating_shift_pair'">
                                <p class="mt-1 text-(--color-text-secondary)">
                                    {{ shiftName(row.config.first_shift_id) }} ↔ {{ shiftName(row.config.second_shift_id) }}
                                </p>
                                <p class="mt-1 text-(--color-text-secondary)">{{ __('planning_rules.alternating_shift_pair_hint') }}</p>
                            </template>
                        </td>
                        <td class="w-px whitespace-nowrap px-3 py-3 align-top">
                            <SelectInput v-if="!FIXED_MODE_TYPES.includes(row.type)" v-model="row.mode" :options="modeOptions" class="w-32" />
                            <span v-else-if="row.type === 'alternating_shift_pair'" class="text-(--color-text-secondary)">{{ __('planning_rules.mode.hard') }}</span>
                            <span v-else class="text-(--color-text-secondary)">-</span>
                        </td>
                        <td class="w-px whitespace-nowrap px-3 py-3 align-top">
                            <NumberInput v-if="!FIXED_MODE_TYPES.includes(row.type) && row.mode === 'soft'" v-model="row.severity" :min="1" :max="10" class="w-24" />
                            <span v-else class="text-(--color-text-secondary)">-</span>
                        </td>
                        <td class="w-px whitespace-nowrap px-3 py-3 align-top">
                            <ButtonDanger
                                type="button"
                                icon="bin"
                                :aria-label="__('planning_rules.delete')"
                                @click="remove(row)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="!rows.length" class="text-sm text-(--color-text-secondary)">
            {{ __('planning_rules.list_empty') }}
        </p>

        <CardSeparator />

        <div data-testid="planning-rule-add" class="space-y-3">
            <LabeledInput :label="__('planning_rules.type')">
                <SelectInput
                    v-model="draft.type"
                    :options="availableTypeOptions"
                    :placeholder="__('planning_rules.select_type')"
                    class="w-full max-w-xs"
                />
            </LabeledInput>

            <NumberInput
                v-if="draft.type === 'max_shifts_per_day'"
                v-model="draft.value"
                :min="1"
                class="w-32"
            />

            <div v-if="draft.type === 'competence_required'" class="flex flex-wrap gap-3">
                <SelectInput
                    v-model="draft.workcenter_id"
                    :options="workcenterOptions"
                    :placeholder="__('planning_rules.select_workcenter')"
                    class="w-full max-w-xs"
                />
                <SelectInput
                    v-model="draft.competence_id"
                    :options="competenceOptions"
                    :placeholder="__('planning_rules.select_competence')"
                    class="w-full max-w-xs"
                />
            </div>

            <div v-if="draft.type === 'business_line_preference'" class="space-y-3">
                <SelectInput
                    v-model="draft.workcenter_id"
                    :options="workcenterOptions"
                    :placeholder="__('planning_rules.select_workcenter')"
                    class="w-full max-w-xs"
                />
                <SelectInput v-model="draft.business_line_id" :options="businessLineOptions" class="w-full max-w-xs" />
            </div>

            <div v-if="draft.type === 'alternating_shift_pair'" class="flex flex-wrap gap-3">
                <SelectInput
                    v-model="draft.first_shift_id"
                    :options="shiftOptions"
                    :placeholder="__('planning_rules.select_shift')"
                    class="w-full max-w-xs"
                />
                <SelectInput
                    v-model="draft.second_shift_id"
                    :options="secondShiftOptions"
                    :placeholder="__('planning_rules.select_shift')"
                    class="w-full max-w-xs"
                />
            </div>

            <div v-if="draft.type && draft.type !== 'equal_workload'" class="flex flex-wrap items-end gap-3">
                <LabeledInput :label="__('planning_rules.mode')">
                    <SelectInput v-if="draft.type !== 'alternating_shift_pair'" v-model="draft.mode" :options="modeOptions" class="w-32" />
                    <span v-else class="text-sm text-(--color-text-secondary)">{{ __('planning_rules.mode.hard') }}</span>
                </LabeledInput>
                <LabeledInput v-if="draft.type !== 'alternating_shift_pair' && draft.mode === 'soft'" :label="__('planning_rules.severity')">
                    <NumberInput v-model="draft.severity" :min="1" :max="10" class="w-24" />
                </LabeledInput>
            </div>

            <ButtonPrimary type="button" icon="plus-circle" :disabled="!canAdd()" @click="add">
                {{ __('planning_rules.add') }}
            </ButtonPrimary>
        </div>
    </div>
</template>
