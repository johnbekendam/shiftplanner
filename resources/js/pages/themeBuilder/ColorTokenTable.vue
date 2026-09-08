<template>
    <!-- A grid of colour pickers: one row per element, one column per state or
         slot. Used by the Chrome and Menu tabs. -->
    <div class="overflow-x-auto px-4" data-testid="color-token-table">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr>
                    <!-- label column absorbs the slack so the colour columns
                         stay tight together on the right; the cell doubles as
                         the table's group name -->
                    <th class="w-full py-2 pr-3 text-left font-semibold text-[var(--color-card-body-text)]">
                        {{ label }}
                    </th>
                    <th
                        v-for="col in columns"
                        :key="col"
                        class="min-w-24 px-2 py-2 text-center font-semibold whitespace-nowrap text-[var(--color-card-body-text)]"
                    >
                        {{ col }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row.label"
                    class="border-t border-[var(--color-card-body-border)]"
                >
                    <th class="py-2.5 pr-3 text-left font-medium whitespace-nowrap text-[var(--color-card-body-text)]">
                        {{ row.label }}
                    </th>
                    <td
                        v-for="(key, i) in row.keys"
                        :key="key ?? `blank-${i}`"
                        class="min-w-24 px-2 py-2.5 text-center"
                    >
                        <ColorTokenPicker
                            v-if="key"
                            :model-value="colors[key][mode]"
                            @update:model-value="colors[key][mode] = $event"
                        />
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import ColorTokenPicker from '@/components/ui/ColorTokenPicker.vue'

defineProps({
    // Fills the top-left header cell — the table's group name. Empty by default.
    label: { type: String, default: '' },
    // Column headers.
    columns: { type: Array, required: true },
    // [{ label, keys: [tokenKey per column; null renders a blank cell] }]
    rows: { type: Array, required: true },
    // The reactive colors map, mutated in place.
    colors: { type: Object, required: true },
    // 'light' | 'dark'
    mode: { type: String, required: true },
})
</script>
