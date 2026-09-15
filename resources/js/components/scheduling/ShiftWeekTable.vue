<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import axios from 'axios'
import Icon from '@/components/ui/Icon.vue'
import { SearchInput } from '@/components/ui/Input'
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
// A floating menu of selectable counts, positioned from the clicked date
// label's own rect — same approach as the assign popover below.
const spotsOptions = Array.from({ length: 13 }, (_, i) => i) // 0..12
const spotsMenuDate = ref(null)
const spotsMenuTriggerEl = ref(null)
const spotsMenuRef = ref(null)
const spotsMenuStyle = ref({})

const spotsMenuCell = computed(() => props.cells.find((c) => c.date === spotsMenuDate.value) ?? null)

function computeSpotsMenuPosition() {
    if (!spotsMenuTriggerEl.value) return
    const rect = spotsMenuTriggerEl.value.getBoundingClientRect()
    spotsMenuStyle.value = { top: `${rect.bottom + 4}px`, left: `${rect.left}px` }
}

function toggleSpotsMenu(cell, event) {
    if (spotsMenuDate.value === cell.date) {
        closeSpotsMenu()
        return
    }
    spotsMenuDate.value = cell.date
    spotsMenuTriggerEl.value = event.currentTarget
    computeSpotsMenuPosition()
}

function closeSpotsMenu() {
    spotsMenuDate.value = null
    spotsMenuTriggerEl.value = null
}

async function selectSpots(value) {
    const cell = spotsMenuCell.value
    closeSpotsMenu()
    if (!cell || value === cell.spots) return
    await putAsync(spotsUrl(cell.date), { spots: value }).catch(() => {})
}

async function resetSpots(cell) {
    await deleteAsync(spotsUrl(cell.date)).catch(() => {})
}

// ── Assignees ───────────────────────────────────────────────────────────
// A floating menu (Freeze/Unfreeze, Remove), same positioning approach as
// the spots menu and the assign popover.
const assignmentMenuAssignment = ref(null)
const assignmentMenuTriggerEl = ref(null)
const assignmentMenuRef = ref(null)
const assignmentMenuStyle = ref({})

function computeAssignmentMenuPosition() {
    if (!assignmentMenuTriggerEl.value) return
    const rect = assignmentMenuTriggerEl.value.getBoundingClientRect()
    assignmentMenuStyle.value = { top: `${rect.bottom + 4}px`, left: `${rect.left}px` }
}

function toggleAssignmentMenu(assignment, event) {
    if (assignmentMenuAssignment.value?.id === assignment.id) {
        closeAssignmentMenu()
        return
    }
    assignmentMenuAssignment.value = assignment
    assignmentMenuTriggerEl.value = event.currentTarget
    computeAssignmentMenuPosition()
}

function closeAssignmentMenu() {
    assignmentMenuAssignment.value = null
    assignmentMenuTriggerEl.value = null
}

async function selectFreeze() {
    const assignment = assignmentMenuAssignment.value
    closeAssignmentMenu()
    if (!assignment) return
    await putAsync(`/scheduling/assignments/${assignment.id}`, { fixed: !assignment.fixed }).catch(() => {})
}

async function selectRemove() {
    const assignment = assignmentMenuAssignment.value
    closeAssignmentMenu()
    if (!assignment) return
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
    if (openAssignDate.value) {
        if (!openTriggerEl.value?.contains(e.target) && !panelRef.value?.contains(e.target)) closeAssign()
    }
    if (spotsMenuDate.value) {
        if (!spotsMenuTriggerEl.value?.contains(e.target) && !spotsMenuRef.value?.contains(e.target)) closeSpotsMenu()
    }
    if (assignmentMenuAssignment.value) {
        if (!assignmentMenuTriggerEl.value?.contains(e.target) && !assignmentMenuRef.value?.contains(e.target)) closeAssignmentMenu()
    }
}

function onScroll() {
    if (openAssignDate.value) computePanelPosition()
    if (spotsMenuDate.value) computeSpotsMenuPosition()
    if (assignmentMenuAssignment.value) computeAssignmentMenuPosition()
}

document.addEventListener('mousedown', onClickOutside)
document.addEventListener('scroll', onScroll, true)

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside)
    document.removeEventListener('scroll', onScroll, true)
})
</script>

<template>
    <table class="w-full table-fixed text-sm">
        <thead>
            <tr class="border-b border-(--color-table-header-separator)">
                <th class="w-8"></th>
                <th v-for="cell in cells" :key="cell.date" class="px-1 py-1 text-left font-medium">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            :data-testid="`spots-${shiftId}-${cell.date}`"
                            @click="(e) => toggleSpotsMenu(cell, e)"
                        >
                            {{ formatDay(cell.date) }}
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
                    class="px-1 py-0.5 text-left"
                    :data-testid="`cell-${shiftId}-${cell.date}-${row}`"
                >
                    <div class="flex h-5 min-w-0 items-center">
                        <template v-if="cellState(cell, row).type === 'filled'">
                            <button
                                type="button"
                                class="inline-flex min-w-0 items-center"
                                :title="cellState(cell, row).assignment.employee_name"
                                @click="(e) => toggleAssignmentMenu(cellState(cell, row).assignment, e)"
                            >
                                <span
                                    v-if="cellState(cell, row).assignment.fixed"
                                    class="inline-flex min-w-0 items-center gap-1 text-(--color-btn-primary-bg)"
                                >
                                    <Icon name="map-pin" class="size-3 shrink-0" />
                                    <span class="truncate">{{ cellState(cell, row).assignment.employee_name }}</span>
                                </span>
                                <span v-else class="block min-w-0 truncate">{{ cellState(cell, row).assignment.employee_name }}</span>
                            </button>
                        </template>
                        <button
                            v-else-if="cellState(cell, row).type === 'open'"
                            type="button"
                            class="inline-flex items-center text-(--color-btn-primary-bg)"
                            :aria-label="__('scheduling.open_spot')"
                            @click="(e) => openAssign(cell.date, e)"
                        >
                            <Icon name="plus-circle" class="size-4" />
                        </button>
                        <span v-else class="text-(--color-text-muted)">—</span>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>

    <Teleport to="body">
        <div
            v-if="spotsMenuDate"
            ref="spotsMenuRef"
            data-testid="spots-menu"
            :style="spotsMenuStyle"
            class="fixed z-50 max-h-48 w-16 overflow-y-auto rounded-md border border-(--color-dropdown-panel-border) bg-(--color-dropdown-panel-bg) p-1 shadow-lg"
        >
            <button
                v-for="n in spotsOptions"
                :key="n"
                type="button"
                class="block w-full rounded px-2 py-1 text-left hover:bg-(--color-dropdown-option-hover-bg)"
                :class="n === spotsMenuCell?.spots ? 'font-semibold text-(--color-btn-primary-bg)' : ''"
                @click="selectSpots(n)"
            >
                {{ n }}
            </button>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="assignmentMenuAssignment"
            ref="assignmentMenuRef"
            data-testid="assignment-menu"
            :style="assignmentMenuStyle"
            class="fixed z-50 w-28 rounded-md border border-(--color-dropdown-panel-border) bg-(--color-dropdown-panel-bg) p-1 shadow-lg"
        >
            <button
                type="button"
                class="block w-full rounded px-2 py-1 text-left hover:bg-(--color-dropdown-option-hover-bg)"
                @click="selectFreeze"
            >
                {{ assignmentMenuAssignment.fixed ? __('scheduling.unfreeze') : __('scheduling.freeze') }}
            </button>
            <button
                type="button"
                class="block w-full rounded px-2 py-1 text-left hover:bg-(--color-dropdown-option-hover-bg)"
                @click="selectRemove"
            >
                {{ __('scheduling.remove') }}
            </button>
        </div>
    </Teleport>

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
