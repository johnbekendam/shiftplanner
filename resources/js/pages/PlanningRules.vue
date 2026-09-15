<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import TabSaveBar from '@/components/ui/TabSaveBar.vue'
import PlanningRuleList from '@/components/PlanningRuleList.vue'
import { useI18n } from '@/composables/useI18n'
import { useUnsavedChangesGuard } from '@/composables/useUnsavedChangesGuard'
import { putAsync, postAsync, deleteAsync } from '@/utils/inertiaAsync'

const __ = useI18n()

const props = defineProps({
    planningRules: { type: Array, default: () => [] }, // { id, type, mode, severity, config }
    workcenters: { type: Array, default: () => [] }, // { id, name }
    shifts: { type: Array, default: () => [] }, // { id, name }
    competences: { type: Array, default: () => [] }, // { id, name }
    businessLines: { type: Array, default: () => [] }, // { id, abbreviation }
})

// A rule's type, and for a scoped type what it targets, is fixed once
// created — only mode/severity and a type's own mutable field (value,
// business_line_ids) can change in place.
const version = ref(0)
const committed = ref(props.planningRules)
const current = ref(props.planningRules)
const saving = ref(false)
const justSaved = ref(false)

function onChange(rows) {
    current.value = rows
}

function ruleEquals(a, b) {
    return a.mode === b.mode && a.severity === b.severity && JSON.stringify(a.config) === JSON.stringify(b.config)
}

function rulePayload(row) {
    return { type: row.type, mode: row.mode, severity: row.severity, ...row.config }
}

const dirty = computed(() => {
    if (current.value.some((r) => r.id === null)) return true
    if (committed.value.some((c) => !current.value.some((r) => r.id === c.id))) return true

    return current.value.some((row) => {
        if (row.id === null) return false
        const orig = committed.value.find((c) => c.id === row.id)
        return orig && !ruleEquals(orig, row)
    })
})

async function save() {
    saving.value = true
    const toDelete = committed.value.filter((c) => !current.value.some((r) => r.id === c.id))
    const toAdd = current.value.filter((r) => r.id === null)
    const toEdit = current.value.filter((r) => {
        if (r.id === null) return false
        const orig = committed.value.find((c) => c.id === r.id)
        return orig && !ruleEquals(orig, r)
    })

    const results = await Promise.allSettled([
        ...toDelete.map((r) => deleteAsync(`/planning-rules/${r.id}`)),
        ...toEdit.map((r) => putAsync(`/planning-rules/${r.id}`, rulePayload(r))),
        ...toAdd.map((r) => postAsync('/planning-rules', rulePayload(r))),
    ])

    saving.value = false
    const ok = results.every((r) => r.status === 'fulfilled')
    if (ok) {
        committed.value = props.planningRules
        current.value = props.planningRules
        version.value++
        justSaved.value = true
        setTimeout(() => { justSaved.value = false }, 2000)
    }
    return ok
}

function cancel() {
    current.value = committed.value
    version.value++
}

useUnsavedChangesGuard(() => dirty.value)
</script>

<template>
    <AppLayout>
        <Head :title="__('planning_rules.title')" />

        <Card class="max-w-3xl">
            <div class="p-6">
                <PlanningRuleList
                    :key="version"
                    :items="committed"
                    :workcenters="workcenters"
                    :shifts="shifts"
                    :competences="competences"
                    :business-lines="businessLines"
                    @update:items="onChange"
                />
                <TabSaveBar :dirty="dirty" :saving="saving" :just-saved="justSaved" @save="save" @cancel="cancel" />
            </div>
        </Card>
    </AppLayout>
</template>
