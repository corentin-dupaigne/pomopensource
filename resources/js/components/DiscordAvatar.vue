<template>
    <span
        class="inline-flex items-center justify-center shrink-0 rounded-full overflow-hidden bg-[#5865F2] text-white font-inter font-semibold select-none"
        :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${size * 0.42}px` }"
        :title="name"
    >
        <!-- Falls back to the initial when the CDN mapping is missing. -->
        <img v-if="!failed" :src="url" :alt="name" class="w-full h-full object-cover" @error="failed = true" />
        <span v-else aria-hidden="true">{{ initial }}</span>
        <span v-if="failed" class="sr-only">{{ name }}</span>
    </span>
</template>

<script>
import { ref, computed } from 'vue';
import { discordAvatarUrl, discordDisplayName } from '../discord.js';

export default {
    props: {
        user: { type: Object, required: true },
        size: { type: Number, default: 32 },
    },
    setup(props) {
        const failed = ref(false);
        const name = computed(() => discordDisplayName(props.user));
        return {
            failed,
            name,
            initial: computed(() => name.value.charAt(0).toUpperCase()),
            url: computed(() => discordAvatarUrl(props.user, props.size * 2)),
        };
    },
};
</script>
