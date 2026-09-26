<script setup>
import { computed, ref, watch } from 'vue'
import Card from '@/components/ui/Card.vue'
import ButtonPrimary from '@/components/ui/ButtonPrimary.vue'
import ButtonSecondary from '@/components/ui/ButtonSecondary.vue'
import { CheckboxInput, SelectInput } from '@/components/ui/Input'
import { formatDate } from '@/utils/date'
import { isoWeekday } from '@/utils/availabilityCalendar'
import { useI18n } from '@/composables/useI18n'

const __ = useI18n()

const props = defineProps({
    open: { type: Boolean, default: false },
    // A dayAvailability() result: { date, holiday, blocked, shifts: [{ shift, defaultLevel, override }] }.
    day: { type: Object, default: null },
    // Read-only (employee change lock).
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'apply'])

const LEVELS = ['available', 'not_preferred', 'unavailable']

// The edit-until-Apply state, seeded from the day each time the dialog opens.
const blocked = ref(false)
const levels = ref({})

function seed() {
    if (!props.day) return
    blocked.value = props.day.blocked
    levels.value = Object.fromEntries(props.day.shifts.map((s) => [s.shift.id, s.override ?? 'default']))
}

watch(() => [props.open, props.day], seed, { immediate: true })

const title = computed(() => props.day
    ? __('availability.day.title', {
        weekday: __(`availability.weekday.${isoWeekday(props.day.date)}`),
        date: formatDate(props.day.date),
    })
    : '')

function optionsFor(entry) {
    return [
        { value: 'default', label: __('availability.day.default', { level: __(`availability.state.${entry.defaultLevel}`) }) },
        ...LEVELS.map((level) => ({ value: level, label: __(`availability.state.${level}`) })),
    ]
}

function reset() {
    blocked.value = false
    levels.value = Object.fromEntries(Object.keys(levels.value).map((id) => [id, 'default']))
}

function apply() {
    const shifts = Object.fromEntries(Object.entries(levels.value).filter(([, level]) => level !== 'default'))
    emit('apply', { date: props.day.date, blocked: blocked.value, shifts })
}
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
                v-if="open && day"
                data-testid="day-availability-dialog"
                class="fixed inset-0 z-50 flex items-center justify-center px-4"
                role="dialog"
                aria-modal="true"
                @keydown.escape.stop="emit('close')"
            >
                <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

                <Card class="relative z-10 w-full max-w-md">
                    <template #header>
                        <div class="px-6 py-4 text-base font-semibold">{{ title }}</div>
                    </template>

                    <div class="space-y-4 px-6 py-5 text-sm">
                        <p
                            v-if="day.holiday"
                            class="rounded-md border border-(--color-badge-warning-border) bg-(--color-badge-warning-bg) px-3 py-2 text-(--color-badge-warning-text)"
                        >
                            {{ __('availability.day.holiday_notice') }}
                        </p>

                        <CheckboxInput v-model="blocked" :disabled="disabled">
                            {{ __('availability.day.block') }}
                        </CheckboxInput>

                        <p v-if="!day.shifts.length" class="text-(--color-text-secondary)">
                            {{ __('availability.day.no_shifts') }}
                        </p>

                        <div
                            v-for="entry in day.shifts"
                            :key="entry.shift.id"
                            :data-testid="`day-shift-${entry.shift.id}`"
                            class="flex items-center justify-between gap-4"
                        >
                            <div>
                                <span class="block font-medium text-(--color-text-primary)">{{ entry.shift.name }}</span>
                                <span class="block text-xs text-(--color-text-secondary)">
                                    {{ entry.shift.start_time }} – {{ entry.shift.end_time }}
                                </span>
                            </div>
                            <SelectInput
                                v-model="levels[entry.shift.id]"
                                :options="optionsFor(entry)"
                                :disabled="disabled || blocked"
                                class="w-48"
                            />
                        </div>
                    </div>

                    <template #footer>
                        <div class="flex items-center justify-between gap-3 px-6 py-4">
                            <ButtonSecondary v-if="!disabled" type="button" @click="reset">
                                {{ __('availability.day.reset') }}
                            </ButtonSecondary>
                            <span v-else />
                            <div class="flex items-center gap-3">
                                <ButtonSecondary type="button" @click="emit('close')">
                                    {{ __('availability.day.cancel') }}
                                </ButtonSecondary>
                                <ButtonPrimary v-if="!disabled" type="button" @click="apply">
                                    {{ __('availability.day.apply') }}
                                </ButtonPrimary>
                            </div>
                        </div>
                    </template>
                </Card>
            </div>
        </Transition>
    </Teleport>
</template>
