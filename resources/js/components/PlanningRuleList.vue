<script setup>
import { reactive, ref, computed, watch } from 'vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonDanger from '@/components/ui/ButtonDanger.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import LabeledInput from '@/components/LabeledInput.vue'
import TagChecklist from '@/components/TagChecklist.vue'
import { SelectInput, NumberInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    // Rows of { id, type, mode, severity, config }.
    items: { type: Array, default: () => [] },
    workcenters: { type: Array, default: () => [] }, // { id, name }
    shifts: { type: Array, default: () => [] }, // { id, name }
    competences: { type: Array, default: () => [] }, // { id, name }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
})

const emit = defineEmits(['update:items'])

const SINGLETON_TYPES = ['max_hours_per_week', 'max_shifts_per_day', 'not_preferred_shift']
const ALL_TYPES = [...SINGLETON_TYPES, 'competence_required', 'business_line_preference']

const typeOptions = computed(() => ALL_TYPES.map((t) => ({ value: t, label: __(`planning_rules.type.${t}`) })))
const modeOptions = computed(() => [
    { value: 'hard', label: __('planning_rules.mode.hard') },
    { value: 'soft', label: __('planning_rules.mode.soft') },
])
const workcenterOptions = computed(() => props.workcenters.map((w) => ({ value: w.id, label: w.name })))
const shiftOptions = computed(() => props.shifts.map((s) => ({ value: s.id, label: s.name })))
const competenceOptions = computed(() => props.competences.map((c) => ({ value: c.id, label: c.name })))
// TagChecklist renders `name`; BusinessLine's payload calls it `abbreviation`.
const businessLineItems = computed(() => props.businessLines.map((bl) => ({ id: bl.id, name: bl.abbreviation })))

const workcenterName = (id) => props.workcenters.find((w) => w.id === id)?.name ?? `#${id}`
const shiftName = (id) => props.shifts.find((s) => s.id === id)?.name ?? `#${id}`
const competenceName = (id) => props.competences.find((c) => c.id === id)?.name ?? `#${id}`

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
        shift_id: null,
        competence_id: null,
        business_line_ids: [],
    }
}

const draft = reactive(freshDraft())

function configFor(type, source) {
    switch (type) {
        case 'max_shifts_per_day':
            return { value: source.value }
        case 'competence_required':
            return { workcenter_id: source.workcenter_id, shift_id: source.shift_id, competence_id: source.competence_id }
        case 'business_line_preference':
            return { workcenter_id: source.workcenter_id, business_line_ids: [...source.business_line_ids] }
        default:
            return {}
    }
}

function canAdd() {
    if (!draft.type) return false
    if (draft.mode === 'soft' && !draft.severity) return false
    if (draft.type === 'max_shifts_per_day' && !draft.value) return false
    if (draft.type === 'competence_required' && (!draft.workcenter_id || !draft.shift_id || !draft.competence_id)) return false
    if (draft.type === 'business_line_preference' && (!draft.workcenter_id || !draft.business_line_ids.length)) return false

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
            mode: draft.mode,
            severity: draft.mode === 'soft' ? draft.severity : null,
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
        <div
            v-for="row in rows"
            :key="row.id ?? row._key"
            data-testid="planning-rule-row"
            class="space-y-3 rounded-lg border border-(--color-border) p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-medium text-(--color-text-primary)">{{ __(`planning_rules.type.${row.type}`) }}</p>

                    <p v-if="row.type === 'competence_required'" class="text-sm text-(--color-text-secondary)">
                        {{ workcenterName(row.config.workcenter_id) }} / {{ shiftName(row.config.shift_id) }}
                        → {{ competenceName(row.config.competence_id) }}
                    </p>
                    <p v-else-if="row.type === 'business_line_preference'" class="text-sm text-(--color-text-secondary)">
                        {{ workcenterName(row.config.workcenter_id) }}
                    </p>
                    <p v-else-if="row.type === 'max_hours_per_week'" class="text-sm text-(--color-text-secondary)">
                        {{ __('planning_rules.max_hours_per_week_hint') }}
                    </p>
                    <p v-else-if="row.type === 'not_preferred_shift'" class="text-sm text-(--color-text-secondary)">
                        {{ __('planning_rules.not_preferred_shift_hint') }}
                    </p>
                </div>
                <ButtonDanger
                    type="button"
                    icon="bin"
                    :aria-label="__('planning_rules.delete')"
                    @click="remove(row)"
                />
            </div>

            <NumberInput
                v-if="row.type === 'max_shifts_per_day'"
                v-model="row.config.value"
                :min="1"
                class="w-32"
            />

            <TagChecklist
                v-if="row.type === 'business_line_preference'"
                :items="businessLineItems"
                :selected-ids="row.config.business_line_ids"
                empty-key="planning_rules.list_empty"
                @update:selected-ids="row.config.business_line_ids = $event"
            />

            <div class="flex flex-wrap items-end gap-3">
                <LabeledInput :label="__('planning_rules.mode')">
                    <SelectInput v-model="row.mode" :options="modeOptions" class="w-32" />
                </LabeledInput>
                <LabeledInput v-if="row.mode === 'soft'" :label="__('planning_rules.severity')">
                    <NumberInput v-model="row.severity" :min="1" :max="10" class="w-24" />
                </LabeledInput>
            </div>
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
                    v-model="draft.shift_id"
                    :options="shiftOptions"
                    :placeholder="__('planning_rules.select_shift')"
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
                <TagChecklist
                    :items="businessLineItems"
                    :selected-ids="draft.business_line_ids"
                    empty-key="planning_rules.list_empty"
                    @update:selected-ids="draft.business_line_ids = $event"
                />
            </div>

            <div v-if="draft.type" class="flex flex-wrap items-end gap-3">
                <LabeledInput :label="__('planning_rules.mode')">
                    <SelectInput v-model="draft.mode" :options="modeOptions" class="w-32" />
                </LabeledInput>
                <LabeledInput v-if="draft.mode === 'soft'" :label="__('planning_rules.severity')">
                    <NumberInput v-model="draft.severity" :min="1" :max="10" class="w-24" />
                </LabeledInput>
            </div>

            <ButtonPrimary type="button" icon="plus-circle" :disabled="!canAdd()" @click="add">
                {{ __('planning_rules.add') }}
            </ButtonPrimary>
        </div>
    </div>
</template>
