<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Card from '@/components/ui/Card.vue'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import Tabs from '@/components/ui/Tabs.vue'
import OrderedNameList from '@/components/OrderedNameList.vue'
import BusinessLineList from '@/components/BusinessLineList.vue'
import ShiftList from '@/components/ShiftList.vue'
import ShiftNoteForm from '@/components/ShiftNoteForm.vue'
import ScheduleNoteForm from '@/components/ScheduleNoteForm.vue'
import PeriodSettingsForm from '@/components/PeriodSettingsForm.vue'
import SaveStatusBadge from '@/components/ui/SaveStatusBadge.vue'
import { useI18n } from '@/composables/useI18n'
import { useSaveStatus } from '@/composables/useSaveStatus'

const __ = useI18n()
const saveStatus = useSaveStatus()

defineProps({
    competences: { type: Array, default: () => [] },
    businessLines: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    shiftNote: { type: String, default: '' },
    scheduleNote: { type: String, default: '' },
    questions: { type: Array, default: () => [] },
    period: { type: Object, default: () => ({}) },
})

const tab = ref('general')
const tabs = computed(() => [
    { value: 'general', label: __('settings.tab.general') },
    { value: 'business_lines', label: __('settings.tab.business_lines') },
    { value: 'shifts', label: __('settings.tab.shifts') },
    { value: 'questions', label: __('settings.tab.questions') },
    { value: 'competences', label: __('settings.tab.competences') },
    { value: 'information', label: __('settings.tab.information') },
])
</script>

<template>
    <AppLayout>
        <Head :title="__('settings.title')" />

        <Card class="max-w-3xl">
            <template #header>
                <Tabs v-model="tab" :tabs="tabs" />
            </template>

            <div v-show="tab === 'business_lines'" data-testid="panel-business-lines" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <BusinessLineList :items="businessLines" endpoint="/settings/business-lines" :save-status="saveStatus" />
            </div>

            <div v-show="tab === 'general'" data-testid="panel-general" class="p-6">
                <PeriodSettingsForm :period="period" />
            </div>

            <div v-show="tab === 'shifts'" data-testid="panel-shifts" class="relative space-y-6 p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <ShiftList :items="shifts" endpoint="/settings/shifts" :save-status="saveStatus" />
                <CardSeparator />
                <ScheduleNoteForm :note="scheduleNote" />
            </div>

            <div v-show="tab === 'information'" data-testid="panel-information" class="p-6">
                <ShiftNoteForm :note="shiftNote" />
            </div>

            <div v-show="tab === 'questions'" data-testid="panel-questions" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <OrderedNameList
                    :items="questions"
                    endpoint="/settings/questions"
                    i18n-prefix="questions"
                    :save-status="saveStatus"
                />
            </div>

            <div v-show="tab === 'competences'" data-testid="panel-competences" class="relative p-6">
                <SaveStatusBadge :status="saveStatus.status.value" />
                <OrderedNameList
                    :items="competences"
                    endpoint="/settings/competences"
                    i18n-prefix="competences"
                    :save-status="saveStatus"
                />
            </div>
        </Card>
    </AppLayout>
</template>
