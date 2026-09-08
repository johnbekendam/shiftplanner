<template>
    <!-- Type specimen: one line per text type on the Text tab. -->
    <div class="flex flex-1 flex-col gap-3 overflow-auto p-1">
        <p class="text-base font-bold" style="color: var(--color-text-heading)">Heading</p>

        <p class="text-xs" style="color: var(--color-text-primary)">
            Normal text carries the main reading content on the content surface.
        </p>

        <p class="text-xs" style="color: var(--color-text-muted)">
            Muted text is for the least prominent labels and hints.
        </p>

        <p class="text-xs">
            <a href="#" class="font-medium" style="color: var(--color-text-link)" @click.prevent>Link normal</a>
            <span class="mx-2" style="color: var(--color-text-muted)">·</span>
            <a
                href="#"
                class="font-medium underline"
                style="color: var(--color-text-link-hover)"
                @click.prevent
                >Link hover</a
            >
        </p>
    </div>
</template>
