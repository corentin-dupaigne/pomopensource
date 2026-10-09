<template>
    <div
        v-if="participants.length > 0"
        class="flex items-center gap-2 min-w-0 py-1 pl-1 pr-3 rounded-full bg-black/30 text-white text-sm font-inter"
        :aria-label="label"
        role="group"
    >
        <div class="flex -space-x-2" aria-hidden="true">
            <DiscordAvatar
                v-for="participant in shown"
                :key="participant.id"
                :user="participant"
                :size="28"
                class="ring-2 ring-black/40"
            />
            <span
                v-if="hidden > 0"
                class="w-7 h-7 rounded-full bg-white/20 ring-2 ring-black/40 flex items-center justify-center text-xs font-semibold"
            >+{{ hidden }}</span>
        </div>
        <span class="truncate">{{ label }}</span>
    </div>
</template>

<script>
import { computed } from 'vue';
import { discordSession } from '../discord.js';
import DiscordAvatar from './DiscordAvatar.vue';

const MAX_SHOWN = 3;

export default {
    components: { DiscordAvatar },
    setup() {
        const participants = computed(() => discordSession.participants);
        const others = computed(() => participants.value.filter((p) => p.id !== discordSession.user?.id).length);

        return {
            participants,
            shown: computed(() => participants.value.slice(0, MAX_SHOWN)),
            hidden: computed(() => Math.max(0, participants.value.length - MAX_SHOWN)),
            label: computed(() => (others.value === 1 ? 'You and 1 other' : `You and ${others.value} others`)),
        };
    },
};
</script>
