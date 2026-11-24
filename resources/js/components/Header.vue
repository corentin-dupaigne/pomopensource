<template>
    <!-- Discord already names the Activity: no logo, and a compact bar. -->
    <header
        class="flex justify-between items-center"
        :class="isDiscordActivity ? 'px-3 py-2 gap-2' : 'px-6 md:px-24 py-6 mb-16 short:py-2 short:mb-2'"
    >
        <div class="flex items-center space-x-2 min-w-0">
            <img
                v-if="!isDiscordActivity"
                src="/images/logo.webp"
                alt="Pomopensource Logo"
                class="h-16 md:h-20 short:h-10" />
            <!-- With others in the call, show them here rather than in a row
                 of their own: short frames need the height for the timer. -->
            <Participants v-else-if="discordSession.participants.length > 1" />
            <template v-else-if="discordSession.user">
                <DiscordAvatar :user="discordSession.user" :size="32" />
                <span class="hidden sm:inline truncate text-sm font-semibold text-white">{{ displayName }}</span>
            </template>
        </div>
        <nav ref="nav" class="relative flex items-center space-x-2" :class="{ 'activity-nav': isDiscordActivity }" aria-label="Main navigation">
            <button
                v-if="notSynced"
                @click="explainNotSynced"
                class="not-synced flex items-center gap-1.5 py-1 px-2.5 rounded-full bg-amber-500/25 text-amber-100 text-xs"
                aria-label="Not synced: why?"
            >
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span>not synced</span>
            </button>
            <button
                v-if="leaderboard"
                @click="$emit('toggleLeaderboard')"
                :aria-label="discordSession.leaderboardNew ? 'Open this server\'s leaderboard (new)' : 'Open this server\'s leaderboard'"
                class="relative flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-trophy" aria-hidden="true"></i>
                <!-- Until the account first opens one. -->
                <span
                    v-if="discordSession.leaderboardNew"
                    class="new-badge absolute -top-1.5 -right-1.5 px-1.5 rounded-full bg-amber-400 text-neutral-900 text-[10px] font-bold uppercase leading-4 shadow"
                    aria-hidden="true"
                >new</span>
            </button>
            <button
                @click="$emit('toggleStats')"
                aria-label="Open statistics"
                class="relative flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
                :class="{ 'hint-glow': statsHint }"
            >
                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                <span v-if="!isDiscordActivity" class="hidden md:inline-block text-sm">stats</span>
            </button>
            <!-- After an account's first pomodoro, if it never opened its
                 stats: once, where they are. Over the page, so nothing moves. -->
            <transition name="hint">
                <!-- The arrow sits outside the blurred box, which would hide it. -->
                <div
                    v-if="statsHint"
                    role="status"
                    class="absolute z-30 top-full right-0 mt-2.5 w-max max-w-[calc(100vw-1.5rem)]"
                    :style="{ '--arrow-right': arrowRight }"
                >
                    <span class="hint-arrow" aria-hidden="true"></span>
                    <div class="flex items-center gap-2 pl-3 pr-1.5 py-1.5 rounded-lg bg-neutral-900/95 backdrop-blur-lg border border-white/20 shadow-2xl text-[13px] leading-snug text-white">
                        <i class="fas fa-check text-emerald-300" aria-hidden="true"></i>
                        <p class="min-w-0"><strong>{{ formatMinutes(statsHint) }}</strong> saved to your account</p>
                        <button
                            type="button"
                            @click="$emit('toggleStats')"
                            class="shrink-0 px-3 py-1.5 rounded-md bg-white/15 hover:bg-white/25 font-semibold transition"
                        >See stats</button>
                        <button
                            type="button"
                            @click="dismissStatsHint"
                            aria-label="Dismiss"
                            class="shrink-0 w-7 h-7 flex items-center justify-center rounded-md text-white/50 hover:text-white hover:bg-white/10 transition"
                        >
                            <i class="fas fa-times text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </transition>
            <button
                @click="$emit('toggleSettings')"
                aria-label="Open settings"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-cog w-4 h-4" aria-hidden="true"></i>
                <span v-if="!isDiscordActivity" class="hidden md:inline-block text-sm">settings</span>
            </button>
            <!-- Inside a Discord Activity, Discord handles sign-in. -->
            <a
                v-if="!auth && !isDiscordActivity"
                href="/login"
                aria-label="Sign in or create account"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-user w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm">login</span>
            </a>
            <a
                v-if="auth && !isDiscordActivity"
                @click.prevent="logout"
                aria-label="Sign out"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition cursor-pointer"
            >
                <i class="fas fa-sign-out-alt w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm">logout</span>
            </a>
        </nav>
    </header>
</template>

<script>
import axios from 'axios';
import { isDiscordActivity, discordSession, discordDisplayName } from '../discord.js';
import DiscordAvatar from './DiscordAvatar.vue';
import Participants from './Participants.vue';
import { useToast } from '../composables/toast.js';
import { statsHint, dismissStatsHint } from '../composables/statsHint.js';

export default {
    name: 'Header',
    components: { DiscordAvatar, Participants },
    data: () => ({ isDiscordActivity, discordSession, statsHint, arrowRight: '1.5rem' }),
    computed: {
        displayName() {
            return discordDisplayName(this.discordSession.user);
        },
    },
    emits: ['toggleStats', 'toggleSettings', 'toggleLeaderboard'],
    props: {
        auth: { default: false },
        // Inside Discord, when sign-in failed and nothing reaches the account.
        notSynced: { type: Boolean, default: false },
        // In a server's call, signed in with Discord.
        leaderboard: { type: Boolean, default: false },
    },
    watch: {
        // The arrow points at the stats button, wherever the buttons after it
        // put it.
        statsHint(minutes) {
            if (!minutes) return;
            this.$nextTick(() => {
                const button = this.$refs.nav?.querySelector('[aria-label="Open statistics"]');
                if (!button) return;
                const nav = this.$refs.nav.getBoundingClientRect();
                const box = button.getBoundingClientRect();
                this.arrowRight = `${nav.right - (box.left + box.right) / 2}px`;
            });
        },
    },
    methods: {
        dismissStatsHint,
        formatMinutes(minutes) {
            const hours = Math.floor(minutes / 60);
            if (!hours) return `${minutes} min`;
            return minutes % 60 ? `${hours}h ${minutes % 60}m` : `${hours}h`;
        },
        explainNotSynced() {
            useToast().error('Discord sign-in did not work: your sessions and subjects are only saved on this device. Reopen the Activity to try again.');
        },
        async logout() {
            try {
                await axios.post('/logout');
                window.location.reload();
            } catch (error) {
                console.error('Logout error:', error);
            }
        }
    }
};
</script>

<style scoped>
/* Touch-sized buttons in the Activity, which is often used on a phone. */
.activity-nav > button {
    min-width: 2.75rem;
    min-height: 2.75rem;
    justify-content: center;
}

/* Short frames with a mouse (Discord on desktop) don't need touch sizes. */
@media (max-height: 500px) and (pointer: fine) {
    .activity-nav > button {
        min-width: 2.25rem;
        min-height: 2.25rem;
    }
}

/* Draws the eye once, then stays still. */
.new-badge {
    animation: new-pop 0.6s ease-out 1s 2;
}

@keyframes new-pop {
    50% { transform: scale(1.25); }
}

@media (prefers-reduced-motion: reduce) {
    .new-badge { animation: none; }
}

/* The stats button, while the hint points at it. */
.hint-glow {
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15), 0 0 24px rgba(255, 255, 255, 0.25);
}

/* Points from the hint up to the stats button. */
.hint-arrow {
    position: absolute;
    z-index: 1;
    top: -5px;
    right: var(--arrow-right);
    width: 12px;
    height: 12px;
    transform: translateX(50%) rotate(45deg);
    background: rgb(23 23 23 / 0.95);
    border-left: 1px solid rgb(255 255 255 / 0.2);
    border-top: 1px solid rgb(255 255 255 / 0.2);
}

.hint-enter-active,
.hint-leave-active {
    transition: opacity 0.2s ease;
}
.hint-enter-from,
.hint-leave-to {
    opacity: 0;
}

.activity-nav > button.not-synced {
    min-width: 0;
}
</style>
