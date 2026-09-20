<script setup>
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

defineProps({
    open: { type: Boolean, default: false },
    // A PlanningTable row: { week, date, day, shift, hours, workcenter, responsible }.
    row: { type: Object, default: null },
    // Shows the Add to calendar button.
    calendarExport: { type: Boolean, default: false },
})

defineEmits(['close', 'add-to-calendar'])
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open && row"
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                role="dialog"
                aria-modal="true"
                @keydown.escape.stop="$emit('close')"
            >
                <div class="absolute inset-0 bg-black/50" @click="$emit('close')" />

                <Card class="relative z-10 w-full max-w-md">
                    <template #header>
                        <div class="px-6 py-4 text-base font-semibold">{{ __('planning.details.title') }}</div>
                    </template>

                    <dl class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 px-6 py-5 text-sm">
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.week') }}</dt>
                        <dd data-testid="shift-detail-week">{{ row.week }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.date') }}</dt>
                        <dd data-testid="shift-detail-date">{{ row.date }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.day') }}</dt>
                        <dd data-testid="shift-detail-day">{{ row.day }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.shift') }}</dt>
                        <dd data-testid="shift-detail-shift">{{ row.shift }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.details.hours') }}</dt>
                        <dd data-testid="shift-detail-hours">{{ row.hours }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.workcenter') }}</dt>
                        <dd data-testid="shift-detail-workcenter">{{ row.workcenter }}</dd>
                        <dt class="text-(--color-text-secondary)">{{ __('planning.table.responsible') }}</dt>
                        <dd data-testid="shift-detail-contact">{{ row.responsible }}</dd>
                    </dl>

                    <template #footer>
                        <div class="flex items-center justify-end gap-3 px-6 py-4">
                            <ButtonSecondary type="button" @click="$emit('close')">
                                {{ __('planning.details.close') }}
                            </ButtonSecondary>
                            <ButtonPrimary
                                v-if="calendarExport"
                                type="button"
                                icon="calendar"
                                @click="$emit('add-to-calendar')"
                            >
                                {{ __('planning.add_to_calendar') }}
                            </ButtonPrimary>
                        </div>
                    </template>
                </Card>
            </div>
        </Transition>
    </Teleport>
</template>
