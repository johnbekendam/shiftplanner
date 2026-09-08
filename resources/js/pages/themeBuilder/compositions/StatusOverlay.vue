<template>
    <!-- Toast held open in a frame corner. Does not dismiss while the Status
         category is active. -->
    <div
        class="absolute right-3 bottom-3 w-44 rounded-lg border px-2.5 py-2 text-[10px] shadow-lg"
        style="
            background-color: var(--color-card-body-bg);
            color: var(--color-card-body-text);
            border-color: var(--color-card-border);
        "
    >
        <p class="font-semibold">Saved</p>
        <p style="color: var(--color-text-muted)">Toast — held open in a corner.</p>
    </div>
</template>
