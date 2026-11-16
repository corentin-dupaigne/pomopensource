<template>
    <div
        class="safe-area fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="leaderboard-title"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content modal-surface flex flex-col w-full max-w-lg mx-4 p-4 sm:p-6 short:p-3 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <!-- Title, periods and close share one row: short frames need the height. -->
            <div class="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-2 pb-4 short:pb-2 border-b border-white/20">
                <h2 id="leaderboard-title" class="text-2xl short:text-xl font-bold font-display text-white">Leaderboard</h2>
                <div class="flex gap-2 mr-auto" role="tablist" aria-label="Period">
                    <button
                        v-for="(label, key) in PERIODS"
                        :key="key"
                        @click="period = key"
                        role="tab"
                        :aria-selected="period === key"
                        aria-controls="leaderboard-list"
                        :class="['px-3 py-1.5 rounded-lg text-sm font-semibold transition',
                                 period === key ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                    >
                        {{ label }}
                    </button>
                </div>
                <button
                    @click="$emit('close')"
                    aria-label="Close leaderboard"
                    class="text-white hover:text-gray-300 transition"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <div id="leaderboard-list" role="tabpanel" class="modal-body flex-1 min-h-0 pt-4 short:pt-2 overflow-y-auto text-white">
                <div v-if="loading && !board" class="flex justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading the leaderboard"></i>
                </div>
                <p v-else-if="failed" class="py-8 text-center text-sm text-white/70">
                    The leaderboard could not load. Try again in a moment.
                </p>
                <template v-else-if="board">
                    <p v-if="board.entries.length === 0" class="py-8 text-center text-sm text-white/70">
                        Nobody has focused in this server's calls {{ EMPTY[period] }}. Start a pomodoro to be first.
                    </p>
                    <ol v-else class="space-y-1" :class="{ 'opacity-60': loading }">
                        <li v-for="entry in rows" :key="entry.user.id" :class="rowClass(entry)">
                            <span class="w-7 shrink-0 text-right text-sm font-semibold tabular-nums" :class="entry.rank <= 3 ? 'text-amber-300' : 'text-white/60'">
                                {{ entry.rank }}
                            </span>
                            <DiscordAvatar :user="entry.user" :size="28" />
                            <span class="flex-1 min-w-0 truncate text-sm">
                                {{ name(entry.user) }}<span v-if="entry.me" class="text-white/60"> (you)</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums">{{ duration(entry.minutes) }}</span>
                        </li>
                    </ol>
                </template>

                <p class="mt-4 short:mt-2 text-xs text-white/50">
                    Time focused in this server's calls.
                    <template v-if="board?.hidden">You are hidden from leaderboards; you can change it in settings.</template>
                    <template v-else>To stay off leaderboards, turn it off in settings.</template>
                </p>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import DiscordAvatar from './DiscordAvatar.vue';
import { discordInstanceId, discordDisplayName } from '../discord.js';

// Rolling windows, so "today" means the same for members everywhere.
const PERIODS = { day: '24 hours', week: '7 days', all: 'All time' };
const EMPTY = { day: 'in the last 24 hours', week: 'in the last 7 days', all: 'yet' };

export default {
    components: { DiscordAvatar },
    emits: ['close'],
    setup() {
        const modalRef = ref(null);
        const period = ref('week');
        const board = ref(null);
        const loading = ref(false);
        const failed = ref(false);

        let latest = 0;
        const load = async () => {
            const request = ++latest;
            loading.value = true;
            failed.value = false;
            try {
                const { data } = await axios.get(`/leaderboard/${discordInstanceId}`, { params: { period: period.value } });
                if (request === latest) board.value = data;
            } catch (error) {
                console.warn('Could not load the leaderboard.', error);
                if (request === latest) failed.value = true;
            } finally {
                if (request === latest) loading.value = false;
            }
        };
        watch(period, load);

        onMounted(async () => {
            load();
            await nextTick();
            modalRef.value?.focus();
        });

        // The viewer's own row comes last when they are below the top.
        const rows = computed(() => board.value
            ? [...board.value.entries, ...(board.value.me ? [board.value.me] : [])]
            : []);

        const rowClass = (entry) => [
            'flex items-center gap-3 px-2 py-1.5 rounded-lg',
            entry.me ? 'bg-white/15' : '',
            board.value.me === entry ? 'mt-3 border-t border-white/10' : '',
        ];

        const duration = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;
            if (hours === 0) return `${rest} min`;
            return rest ? `${hours} h ${rest} min` : `${hours} h`;
        };

        return { PERIODS, EMPTY, modalRef, period, board, loading, failed, rows, rowClass, duration, name: discordDisplayName };
    },
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    max-height: min(90vh, calc(100% - 2rem));
    overflow: hidden;
}
</style>
