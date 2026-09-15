<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'
import Icon from '@/components/ui/Icon.vue'
import { NumberInput, SearchInput } from '@/components/ui/Input'
import { useI18n } from '@/composables/useI18n'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    workcenterId: { type: Number, required: true },
    shiftId: { type: Number, required: true },
    // 7 { date, spots, overridden, assignments: [{id, employee_id, employee_name, fixed}] }, Mon..Sun.
    cells: { type: Array, required: true },
})

const maxSpots = computed(() => Math.max(0, ...props.cells.map((c) => c.spots)))
const rows = computed(() => Array.from({ length: maxSpots.value }, (_, i) => i))

function sortedAssignments(cell) {
    return [...cell.assignments].sort((a, b) => (b.fixed - a.fixed) || a.employee_name.localeCompare(b.employee_name))
}

function cellState(cell, row) {
    const sorted = sortedAssignments(cell)
    if (row < sorted.length) return { type: 'filled', assignment: sorted[row] }
    if (row < cell.spots) return { type: 'open' }
    return { type: 'dash' }
}

function formatDay(dateStr) {
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', day: 'numeric' })
}

function spotsUrl(date) {
    return `/scheduling/spots/${props.workcenterId}/${props.shiftId}/${date}`
}

// ── Spot count ──────────────────────────────────────────────────────────
const editingSpotsDate = ref(null)
const draftSpots = ref(0)

function startEditSpots(cell) {
    draftSpots.value = cell.spots
    editingSpotsDate.value = cell.date
}

function stopEditSpots() {
    editingSpotsDate.value = null
}

async function commitSpots(cell, value) {
    if (value === cell.spots) return
    await putAsync(spotsUrl(cell.date), { spots: value }).catch(() => {})
}

async function resetSpots(cell) {
    await deleteAsync(spotsUrl(cell.date)).catch(() => {})
}

// ── Assignees ───────────────────────────────────────────────────────────
const expandedAssignmentId = ref(null)

function toggleExpanded(assignmentId) {
    expandedAssignmentId.value = expandedAssignmentId.value === assignmentId ? null : assignmentId
}

async function toggleFixed(assignment) {
    await putAsync(`/scheduling/assignments/${assignment.id}`, { fixed: !assignment.fixed }).catch(() => {})
}

async function removeAssignment(assignment) {
    await deleteAsync(`/scheduling/assignments/${assignment.id}`).catch(() => {})
}

// ── Assign (open spot) ─────────────────────────────────────────────────
// Positioned from the clicked trigger's own rect, same approach the old
// SchedulingCell used to escape the table's overflow clipping.
const openAssignDate = ref(null)
const openTriggerEl = ref(null)
const eligible = ref([])
const searchTerm = ref('')
const panelRef = ref(null)
const panelStyle = ref({})

function computePanelPosition() {
    if (!openTriggerEl.value) return
    const rect = openTriggerEl.value.getBoundingClientRect()
    panelStyle.value = { top: `${rect.bottom + 4}px`, left: `${rect.left}px` }
}

async function openAssign(date, event) {
    openAssignDate.value = date
    openTriggerEl.value = event.currentTarget
    searchTerm.value = ''
    computePanelPosition()
    const response = await axios.get('/scheduling/eligible-employees', {
        params: { workcenter_id: props.workcenterId, shift_id: props.shiftId, date },
    })
    eligible.value = response.data
}

function closeAssign() {
    openAssignDate.value = null
    openTriggerEl.value = null
}

const filteredEligible = computed(() => {
    const q = searchTerm.value.trim().toLowerCase()
    if (!q) return eligible.value
    return eligible.value.filter((e) => e.name.toLowerCase().includes(q))
})

async function assign(employee) {
    const date = openAssignDate.value
    closeAssign()
    await postAsync('/scheduling/assignments', {
        employee_id: employee.id,
        workcenter_id: props.workcenterId,
        shift_id: props.shiftId,
        date,
    }).catch(() => {})
}

function onClickOutside(e) {
    if (!openAssignDate.value) return
    if (openTriggerEl.value?.contains(e.target)) return
    if (panelRef.value?.contains(e.target)) return
    closeAssign()
}

function onScroll() {
    if (openAssignDate.value) computePanelPosition()
}

document.addEventListener('mousedown', onClickOutside)
document.addEventListener('scroll', onScroll, true)

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside)
    document.removeEventListener('scroll', onScroll, true)
})
</script>

<template>
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="w-8"></th>
                <th v-for="cell in cells" :key="cell.date" class="px-1 py-1 text-center font-medium">
                    <div class="text-xs text-(--color-text-secondary)">{{ formatDay(cell.date) }}</div>
                    <div class="flex items-center justify-center gap-1">
                        <span v-if="editingSpotsDate === cell.date" @focusout="stopEditSpots">
                            <NumberInput
                                :model-value="draftSpots"
                                :min="0"
                                class="w-14"
                                @update:model-value="(v) => commitSpots(cell, v)"
                            />
                        </span>
                        <button
                            v-else
                            type="button"
                            :data-testid="`spots-${shiftId}-${cell.date}`"
                            @click="startEditSpots(cell)"
                        >
                            {{ cell.spots }}
                        </button>
                        <button
                            v-if="cell.overridden"
                            type="button"
                            :aria-label="__('scheduling.reset_spots')"
                            @click="resetSpots(cell)"
                        >
                            <Icon name="arrow-uturn-left" class="size-3" />
                        </button>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="row in rows" :key="row">
                <td></td>
                <td
                    v-for="cell in cells"
                    :key="cell.date"
                    class="px-1 py-0.5 text-center"
                    :data-testid="`cell-${shiftId}-${cell.date}-${row}`"
                >
                    <template v-if="cellState(cell, row).type === 'filled'">
                        <div
                            v-if="expandedAssignmentId === cellState(cell, row).assignment.id"
                            class="flex items-center justify-center gap-1"
                        >
                            <button
                                type="button"
                                :aria-label="__('scheduling.toggle_fixed')"
                                @click="toggleFixed(cellState(cell, row).assignment)"
                            >
                                <Icon
                                    name="map-pin"
                                    class="size-3"
                                    :class="cellState(cell, row).assignment.fixed
                                        ? 'text-(--color-btn-primary-bg)'
                                        : 'text-(--color-text-secondary)'"
                                />
                            </button>
                            <span>{{ cellState(cell, row).assignment.employee_name }}</span>
                            <button
                                type="button"
                                :aria-label="__('scheduling.remove')"
                                @click="removeAssignment(cellState(cell, row).assignment)"
                            >
                                <Icon name="x-mark" class="size-3" />
                            </button>
                        </div>
                        <button v-else type="button" @click="toggleExpanded(cellState(cell, row).assignment.id)">
                            {{ cellState(cell, row).assignment.employee_name }}
                        </button>
                    </template>
                    <button
                        v-else-if="cellState(cell, row).type === 'open'"
                        type="button"
                        class="text-(--color-btn-primary-bg)"
                        @click="(e) => openAssign(cell.date, e)"
                    >
                        {{ __('scheduling.open_spot') }}
                    </button>
                    <span v-else class="text-(--color-text-muted)">—</span>
                </td>
            </tr>
        </tbody>
    </table>

    <Teleport to="body">
        <div
            v-if="openAssignDate"
            ref="panelRef"
            data-testid="assign-popover"
            :style="panelStyle"
            class="fixed z-50 w-48 rounded-md border border-(--color-dropdown-panel-border) bg-(--color-dropdown-panel-bg) p-2 shadow-lg"
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
    </Teleport>
</template>
