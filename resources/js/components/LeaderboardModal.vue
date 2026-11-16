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

            <div
                id="leaderboard-list"
                ref="bodyRef"
                role="tabpanel"
                class="modal-body flex-1 min-h-0 pt-4 short:pt-2 overflow-y-auto text-white"
                :class="{ 'more-below': moreBelow }"
                @scroll="checkMoreBelow"
            >
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
                    <div v-else :class="{ 'opacity-60': loading }">
                    <!-- The server's time together comes first: the board is
                         a group's, not a contest between strangers. -->
                    <p class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 rounded-lg bg-white/10 px-3 py-2 short:py-1.5 text-sm text-white/60">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-clock text-xs" aria-hidden="true"></i>
                            <span><strong class="font-bold text-white tabular-nums">{{ hours(board.together.minutes) }} h</strong> focused together</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-users text-xs" aria-hidden="true"></i>
                            <span><strong class="font-bold text-white tabular-nums">{{ board.together.members }}</strong> {{ board.together.members === 1 ? 'member' : 'members' }}</span>
                        </span>
                    </p>

                    <!-- Podium: 2nd, 1st, 3rd. Height and size follow the rank, so equal time stands level.
                         Smaller in a short frame, to leave the list some room. -->
                    <ol class="podium mt-4 short:mt-2 grid grid-cols-3 items-end gap-2" aria-label="Top 3">
                        <li
                            v-for="entry in podium"
                            :key="entry.user.id"
                            class="flex flex-col items-center min-w-0"
                            :style="{ order: PODIUM_ORDER[entry.place] }"
                        >
                            <DiscordAvatar :user="entry.user" :size="entry.rank === 1 ? sizes.first : sizes.other" :class="entry.rank === 1 ? 'ring-2 ring-white/40' : ''" />
                            <span class="mt-2 short:mt-1 max-w-full truncate font-semibold" :class="entry.rank === 1 ? 'text-base short:text-sm' : 'text-sm short:text-xs'">
                                {{ name(entry.user) }}<span v-if="entry.me" class="text-white/60 font-normal"> (you)</span>
                            </span>
                            <span class="tabular-nums text-white/60" :class="entry.rank === 1 ? 'text-sm short:text-xs' : 'text-xs short:text-[11px]'">{{ duration(entry.minutes) }}</span>
                            <div
                                class="pedestal mt-2 short:mt-1 w-full flex items-center justify-center rounded-t-lg font-bold text-white"
                                :class="[entry.rank === 1 ? 'bg-white/20' : 'bg-white/10', `pedestal-${entry.rank - 1}`]"
                            >
                                <span class="flex items-center gap-1.5">
                                    <i class="fas fa-trophy pedestal-trophy" :class="MEDALS[entry.rank - 1]" aria-hidden="true"></i>
                                    <span><span class="sr-only">Rank</span>{{ entry.rank }}</span>
                                </span>
                            </div>
                        </li>
                    </ol>

                    <ol v-if="rows.length" class="mt-3 short:mt-2 space-y-1 short:space-y-0.5" aria-label="Everyone else">
                        <li v-for="entry in rows" :key="entry.user.id" :class="rowClass(entry)">
                            <span class="w-7 shrink-0 text-right text-sm font-semibold tabular-nums text-white/60">
                                <template v-if="entry.rank <= 3">
                                    <i class="fas fa-trophy" :class="MEDALS[entry.rank - 1]" aria-hidden="true"></i>
                                    <span class="sr-only">{{ entry.rank }}</span>
                                </template>
                                <template v-else>{{ entry.rank }}</template>
                            </span>
                            <DiscordAvatar :user="entry.user" :size="short ? 24 : 28" />
                            <span class="flex-1 min-w-0 truncate text-sm">
                                {{ name(entry.user) }}<span v-if="entry.me" class="text-white/60"> (you)</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums">{{ duration(entry.minutes) }}</span>
                        </li>
                    </ol>
                    </div>
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
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import DiscordAvatar from './DiscordAvatar.vue';
import { discordInstanceId, discordDisplayName } from '../discord.js';

// Rolling windows, so "today" means the same for members everywhere.
const PERIODS = { day: '24 hours', week: '7 days', all: 'All time' };
const EMPTY = { day: 'in the last 24 hours', week: 'in the last 7 days', all: 'yet' };

// The trophies are the one accent, gold, silver and bronze by rank: two
// first at equal time both get gold.
const MEDALS = ['text-amber-300', 'text-slate-300', 'text-orange-400'];
// The first in the middle, the second on its left, the third on its right.
const PODIUM_ORDER = [2, 1, 3];

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

        const podium = computed(() => (board.value?.entries ?? []).slice(0, 3).map((entry, place) => ({ ...entry, place })));

        // A short frame (Discord in a small window) gets a smaller podium.
        const shortQuery = window.matchMedia('(max-height: 500px)');
        const short = ref(shortQuery.matches);
        const onShortChange = (event) => { short.value = event.matches; };
        shortQuery.addEventListener('change', onShortChange);
        onUnmounted(() => shortQuery.removeEventListener('change', onShortChange));
        const sizes = computed(() => short.value ? { first: 36, other: 28 } : { first: 64, other: 48 });

        // After the podium; the viewer's own row comes last when they are below the top.
        const rows = computed(() => board.value
            ? [...board.value.entries.slice(3), ...(board.value.me ? [board.value.me] : [])]
            : []);

        // Fade the bottom while there is more to scroll to: a list cut at the
        // frame's edge otherwise looks like all there is.
        const bodyRef = ref(null);
        const moreBelow = ref(false);
        const checkMoreBelow = () => {
            const body = bodyRef.value;
            moreBelow.value = Boolean(body) && body.scrollHeight - body.scrollTop - body.clientHeight > 4;
        };
        watch([board, short], () => nextTick(checkMoreBelow));
        window.addEventListener('resize', checkMoreBelow);
        onUnmounted(() => window.removeEventListener('resize', checkMoreBelow));

        const rowClass = (entry) => [
            'flex items-center gap-3 px-2 py-1.5 short:py-1 rounded-lg',
            entry.me ? 'bg-white/15' : '',
            board.value.me === entry ? 'mt-3 border-t border-white/10' : '',
        ];

        const duration = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;
            if (hours === 0) return `${rest} min`;
            return rest ? `${hours} h ${rest} min` : `${hours} h`;
        };

        // As the stats summary writes it: 2.3 h.
        const hours = (minutes) => Math.round(minutes / 6) / 10;

        return {
            hours,
            PERIODS, EMPTY, MEDALS, PODIUM_ORDER, modalRef, period, board, loading, failed,
            podium, rows, sizes, short, bodyRef, moreBelow, checkMoreBelow, rowClass, duration, name: discordDisplayName,
        };
    },
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    max-height: min(90vh, calc(100% - 2rem));
    overflow: hidden;
}

.pedestal { font-size: 1.125rem; }
.pedestal-trophy { font-size: 0.85em; }
.pedestal-0 { height: 3.5rem; font-size: 1.5rem; }
.pedestal-1 { height: 2.75rem; }
.pedestal-2 { height: 2.25rem; }

@media (max-height: 500px) {
    .pedestal { margin-top: 0.25rem; font-size: 0.8rem; }
    .pedestal-0 { height: 1.75rem; font-size: 0.9rem; }
    .pedestal-1 { height: 1.375rem; }
    .pedestal-2 { height: 1.125rem; }
}

.more-below {
    mask-image: linear-gradient(to bottom, black calc(100% - 2.5rem), transparent);
}
</style>
