<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import CardSeparator from '@/components/ui/CardSeparator.vue'
import ScreenLinkRow from '@/components/ScreenLinkRow.vue'
import { useI18n } from '@/composables/useI18n'

// The Settings Screens tab: every link that opens a page without a login.
// The roster link first, then one live screen per active workcenter.
// See features/settings-screens-tab/.
const __ = useI18n()

const props = defineProps({
    rosterUrl: { type: String, required: true },
    workcenters: { type: Array, default: () => [] }, // { id, name, archived_at, live_url }, in position order
})

// An archived workcenter's link returns 404, so it gets no row.
const activeWorkcenters = computed(() => props.workcenters.filter((w) => !w.archived_at))

const visitOptions = { preserveScroll: true, preserveState: true }

function regenerateRoster() {
    router.post('/settings/roster-token', {}, visitOptions)
}

function regenerateLive(id) {
    router.post(`/settings/workcenters/${id}/live-token`, {}, visitOptions)
}
</script>

<template>
    <div>
        <ScreenLinkRow
            data-testid="screen-row-roster"
            :title="__('screens.roster.title')"
            :help="__('screens.roster.help')"
            :url="rosterUrl"
            :regenerate-title="__('screens.roster.regenerate_title')"
            :regenerate-body="__('screens.roster.regenerate_body')"
            @regenerate="regenerateRoster"
        />

        <CardSeparator />

        <div class="space-y-1">
            <h2 class="text-sm font-semibold text-(--color-text-primary)">{{ __('screens.live.title') }}</h2>
            <p class="text-sm text-(--color-text-secondary)">{{ __('screens.live.help') }}</p>
        </div>

        <div class="mt-3 divide-y divide-(--color-table-row-separator)">
            <ScreenLinkRow
                v-for="workcenter in activeWorkcenters"
                :key="workcenter.id"
                class="py-2"
                :data-testid="`screen-row-workcenter-${workcenter.id}`"
                :title="workcenter.name"
                :url="workcenter.live_url"
                :regenerate-title="__('screens.live.regenerate_title')"
                :regenerate-body="__('screens.live.regenerate_body')"
                @regenerate="regenerateLive(workcenter.id)"
            />
        </div>
        <p v-if="!activeWorkcenters.length" class="text-sm text-(--color-text-secondary)">
            {{ __('screens.live.empty') }}
        </p>
    </div>
</template>
