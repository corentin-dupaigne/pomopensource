<template>
    <!-- The month shown in the calendar, and the way to the ones before it.
         Compact in the stats' tab row of short frames: no total there. -->
    <div class="flex items-center" :class="compact ? 'justify-center gap-0.5' : 'justify-between'">
        <button
            type="button"
            @click="$emit('shift', -1)"
            aria-label="Previous month"
            class="flex items-center justify-center rounded text-white/80 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
            :class="compact ? 'w-7 h-7' : 'w-11 h-11 -ml-3'"
        >
            <i class="fas fa-chevron-left" :class="compact ? 'text-xs' : 'text-sm'" aria-hidden="true"></i>
        </button>
        <p class="font-bold text-white text-center whitespace-nowrap" :class="compact ? 'min-w-[5rem] text-sm' : ''" aria-live="polite">
            {{ label }}<span v-if="!compact && minutes" class="font-normal text-white/70"> · {{ formatMinutes(minutes) }}</span>
        </p>
        <button
            type="button"
            @click="$emit('shift', 1)"
            :disabled="isCurrent"
            aria-label="Next month"
            class="flex items-center justify-center rounded text-white/80 hover:text-white transition disabled:text-white/20 disabled:cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
            :class="compact ? 'w-7 h-7' : 'w-11 h-11 -mr-3'"
        >
            <i class="fas fa-chevron-right" :class="compact ? 'text-xs' : 'text-sm'" aria-hidden="true"></i>
        </button>
    </div>
</template>

<script>
import { formatMinutes } from '../composables/duration.js';

export default {
    props: {
        // The first day of the month shown.
        month: { type: Date, required: true },
        // Its time focused, shown beside its name when not compact.
        minutes: { type: Number, default: 0 },
        compact: { type: Boolean, default: false },
    },
    emits: ['shift'],
    computed: {
        label() {
            return this.month.toLocaleDateString('default', {
                month: 'long',
                ...(this.month.getFullYear() !== new Date().getFullYear() && { year: 'numeric' }),
            });
        },
        isCurrent() {
            const now = new Date();
            return this.month.getFullYear() === now.getFullYear() && this.month.getMonth() === now.getMonth();
        },
    },
    methods: { formatMinutes },
};
</script>
