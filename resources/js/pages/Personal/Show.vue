<script setup>
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import CenteredLayout from '@/layouts/CenteredLayout.vue'
import Tabs from '@/components/ui/Tabs.vue'
import EmployeeFields from '@/components/EmployeeFields.vue'
import AvailabilityGrid from '@/components/AvailabilityGrid.vue'
import HolidayList from '@/components/HolidayList.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    token: { type: String, required: true },
    employee: { type: Object, required: true },
    holidays: { type: Array, default: () => [] },
    availability: { type: Array, default: () => [] },
})

const form = useForm({
    name: props.employee.name,
    email: props.employee.email,
    weekly_hours: props.employee.weekly_hours,
})

const tab = ref('details')
const tabs = computed(() => [
    { value: 'details', label: __('availability.tab.details') },
    { value: 'availability', label: __('availability.tab.availability') },
])

function save() {
    form
        .transform((data) => ({ weekly_hours: data.weekly_hours }))
        .put(`/personal/${props.token}`, { preserveScroll: true })
}
</script>

<template>
    <CenteredLayout>
        <Head :title="__('personal.title')" />

        <template #header>
            <Tabs v-model="tab" :tabs="tabs" />
        </template>

        <div v-show="tab === 'details'" data-testid="panel-details" class="space-y-5">
            <div class="space-y-1">
                <p class="text-sm font-medium text-(--color-text-primary)">
                    {{ __('personal.greeting', { name: employee.name }) }}
                </p>
                <p class="text-sm text-(--color-text-secondary)">{{ __('personal.intro') }}</p>
            </div>

            <form class="space-y-5" @submit.prevent="save">
                <EmployeeFields :form="form" readonly-identity />

                <div class="flex items-center justify-end gap-3">
                    <span v-if="form.recentlySuccessful" class="text-sm text-(--color-badge-success-text)">
                        {{ __('personal.saved') }}
                    </span>
                    <ButtonPrimary type="submit" :disabled="form.processing">
                        {{ __('personal.action.save') }}
                    </ButtonPrimary>
                </div>
            </form>
        </div>

        <div v-show="tab === 'availability'" data-testid="panel-availability" class="space-y-8">
            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-(--color-text-primary)">
                    {{ __('availability.grid.heading') }}
                </h3>
                <AvailabilityGrid :availability="availability" :endpoint="`/personal/${token}/availability`" />
            </section>
            <section class="space-y-3">
                <h3 class="text-sm font-semibold text-(--color-text-primary)">
                    {{ __('availability.holidays.heading') }}
                </h3>
                <HolidayList :holidays="holidays" :endpoint="`/personal/${token}/holidays`" />
            </section>
        </div>
    </CenteredLayout>
</template>
