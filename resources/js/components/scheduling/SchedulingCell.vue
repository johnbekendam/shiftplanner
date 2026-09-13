<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import Icon from '@/components/ui/Icon.vue'
import { NumberInput, SearchInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    workcenterId: { type: Number, required: true },
    shiftId: { type: Number, required: true },
    date: { type: String, required: true },
    // { spots, overridden, assignments: [{ id, employee_id, employee_name, fixed }] }
    cell: { type: Object, required: true },
})

const baseUrl = `/scheduling/spots/${props.workcenterId}/${props.shiftId}/${props.date}`

// ── Spot count ──────────────────────────────────────────────────────────
const editingSpots = ref(false)
const draftSpots = ref(props.cell.spots)

function startEditSpots() {
    draftSpots.value = props.cell.spots
    editingSpots.value = true
}

function stopEditSpots() {
    editingSpots.value = false
}

// NumberInput only emits update:modelValue on commit (Enter/Tab/blur), never
// per keystroke, so this alone is "the field was committed to a new value" —
// no separate blur listener needed for the write itself. Closing edit mode
// on a no-op blur (nothing typed, so no commit event fires) is handled by
// the wrapping @focusout below instead.
async function commitSpots(value) {
    if (value === props.cell.spots) return
    await putAsync(baseUrl, { spots: value }).catch(() => {})
}

async function resetSpots() {
    await deleteAsync(baseUrl).catch(() => {})
}

// ── Assignees ───────────────────────────────────────────────────────────
async function toggleFixed(assignment) {
    await putAsync(`/scheduling/assignments/${assignment.id}`, { fixed: !assignment.fixed }).catch(() => {})
}

async function removeAssignment(assignment) {
    await deleteAsync(`/scheduling/assignments/${assignment.id}`).catch(() => {})
}

// ── Add employee ────────────────────────────────────────────────────────
const addOpen = ref(false)
const eligible = ref([])
const searchTerm = ref('')

async function openAdd() {
    addOpen.value = true
    searchTerm.value = ''
    const response = await axios.get('/scheduling/eligible-employees', {
        params: { workcenter_id: props.workcenterId, shift_id: props.shiftId, date: props.date },
    })
    eligible.value = response.data
}

function closeAdd() {
    addOpen.value = false
}

const filteredEligible = computed(() => {
    const q = searchTerm.value.trim().toLowerCase()
    if (!q) return eligible.value
    return eligible.value.filter((e) => e.name.toLowerCase().includes(q))
})

async function assign(employee) {
    closeAdd()
    await postAsync('/scheduling/assignments', {
        employee_id: employee.id,
        workcenter_id: props.workcenterId,
        shift_id: props.shiftId,
        date: props.date,
    }).catch(() => {})
}
</script>

<template>
    <div>
        <div class="flex items-center gap-1 text-xs text-(--color-text-secondary)">
            <span>{{ cell.assignments.length }}/</span>
            <span v-if="editingSpots" @focusout="stopEditSpots">
                <NumberInput
                    :model-value="draftSpots"
                    :min="0"
                    class="w-14"
                    @update:model-value="commitSpots"
                />
            </span>
            <button v-else type="button" :data-testid="`spots-${shiftId}-${date}`" @click="startEditSpots">
                {{ cell.spots }}
            </button>
            <button
                v-if="cell.overridden"
                type="button"
                :aria-label="__('scheduling.reset_spots')"
                @click="resetSpots"
            >
                <Icon name="arrow-uturn-left" class="size-3" />
            </button>
        </div>

        <ul class="space-y-0.5">
            <li v-for="assignment in cell.assignments" :key="assignment.id" class="flex items-center gap-1">
                <button
                    type="button"
                    :aria-label="__('scheduling.toggle_fixed')"
                    @click="toggleFixed(assignment)"
                >
                    <Icon
                        name="map-pin"
                        class="size-3"
                        :class="assignment.fixed ? 'text-(--color-btn-primary-bg)' : 'text-(--color-text-secondary)'"
                    />
                </button>
                <span>{{ assignment.employee_name }}</span>
                <button type="button" :aria-label="__('scheduling.remove')" @click="removeAssignment(assignment)">
                    <Icon name="x-mark" class="size-3" />
                </button>
            </li>
        </ul>

        <div class="relative">
            <button type="button" class="text-xs text-(--color-btn-primary-bg)" @click="addOpen ? closeAdd() : openAdd()">
                + {{ __('scheduling.add') }}
            </button>
            <div
                v-if="addOpen"
                data-testid="add-popover"
                class="absolute z-10 mt-1 w-48 rounded-md border border-(--color-dropdown-panel-border) bg-(--color-dropdown-panel-bg) p-2 shadow-lg"
            >
                <SearchInput v-model="searchTerm" class="w-full" />
                <ul class="mt-1 max-h-40 overflow-y-auto">
                    <li v-for="employee in filteredEligible" :key="employee.id">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-1 rounded px-1 py-1 text-left hover:bg-(--color-dropdown-option-hover-bg)"
                            @click="assign(employee)"
                        >
                            <span>{{ employee.name }}</span>
                            <Icon
                                v-if="employee.not_preferred"
                                name="exclamation-triangle"
                                class="size-3 shrink-0 text-(--color-badge-warning-text)"
                            />
                        </button>
                    </li>
                    <li v-if="!filteredEligible.length" class="px-1 py-1 text-(--color-text-secondary)">
                        {{ __('scheduling.no_eligible_employees') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
