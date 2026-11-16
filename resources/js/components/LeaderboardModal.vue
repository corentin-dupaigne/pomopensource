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
            <div class="shrink-0 pb-4 short:pb-2 border-b border-white/20">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <h2 id="leaderboard-title" class="text-2xl short:text-xl font-bold font-display text-white">Leaderboard</h2>
                    <div class="flex gap-2 mr-auto" role="tablist" aria-label="Period" @keydown="onTabKey">
                        <button
                            v-for="(label, key) in PERIODS"
                            :key="key"
                            :ref="(el) => { if (el) tabRefs[key] = el; }"
                            @click="period = key"
                            role="tab"
                            :aria-selected="period === key"
                            :tabindex="period === key ? 0 : -1"
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
            </div>

            <div
                id="leaderboard-list"
                ref="bodyRef"
                role="tabpanel"
                class="modal-body flex-auto min-h-0 pt-4 short:pt-2 overflow-y-auto text-white"
                :class="{ 'more-below': moreBelow }"
                @scroll="checkMoreBelow"
            >
                <div v-if="loading && !board" class="flex justify-center py-8">
                    <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading the leaderboard"></i>
                </div>
                <div v-else-if="failed" class="flex flex-col items-center gap-3 py-8 text-center text-sm text-white/70">
                    <p>The leaderboard could not load.</p>
                    <button @click="load" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/10 text-white hover:bg-white/20 transition">
                        <i class="fas fa-rotate-right mr-1.5" aria-hidden="true"></i>Try again
                    </button>
                </div>
                <template v-else-if="board">
                    <div v-if="board.entries.length === 0" class="flex flex-col items-center gap-3 py-8 text-center text-sm text-white/70">
                        <p>Nobody has focused in this server's calls {{ EMPTY[period] }}. Start a pomodoro to be first.</p>
                        <button @click="invite" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white/10 text-white hover:bg-white/20 transition">
                            <i class="fas fa-user-plus mr-1.5" aria-hidden="true"></i>Invite a friend
                        </button>
                    </div>
                    <div v-else :class="{ 'opacity-60': loading }">
                    <!-- The server's time together comes first: the board is
                         a group's, not a contest between strangers. -->
                    <p class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 rounded-lg bg-white/10 px-3 py-2 short:py-1.5 text-sm text-white/60">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-clock text-xs" aria-hidden="true"></i>
                            <span><strong class="font-bold text-white tabular-nums">{{ duration(board.together.minutes) }}</strong> focused together</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-users text-xs" aria-hidden="true"></i>
                            <span><strong class="font-bold text-white tabular-nums">{{ board.together.members }}</strong> {{ board.together.members === 1 ? 'member' : 'members' }}</span>
                        </span>
                    </p>
                    <p v-if="board.hidden" class="mt-2 text-center text-xs text-white/50">
                        <i class="fas fa-eye-slash mr-1" aria-hidden="true"></i>You are hidden from leaderboards (settings).
                    </p>

                    <!-- Podium: 2nd, 1st, 3rd. Height and size follow the rank, so equal time stands level.
                         Smaller in a short frame, to leave the list some room. A place nobody
                         holds yet asks to bring a friend: most calls have one person. -->
                    <ol class="podium mt-4 short:mt-2 grid grid-cols-3 items-end gap-2 border-b border-white/20" aria-label="Top 3">
                        <li
                            v-for="slot in podium"
                            :key="slot.entry ? slot.entry.user.id : `free-${slot.place}`"
                            class="flex flex-col items-center min-w-0"
                            :style="{ order: PODIUM_ORDER[slot.place] }"
                        >
                            <template v-if="slot.entry">
                                <DiscordAvatar
                                    :user="slot.entry.user"
                                    :size="slot.rank === 1 ? sizes.first : sizes.other"
                                    :class="slot.rank === 1 ? 'ring-2 ring-white/40' : ''"
                                />
                                <span class="mt-2 short:mt-1 max-w-full truncate font-semibold" :class="slot.rank === 1 ? 'text-base short:text-sm' : 'text-sm short:text-xs'">
                                    {{ name(slot.entry.user) }}<span v-if="slot.entry.me" class="text-white/60 font-normal"> (you)</span>
                                </span>
                                <span class="tabular-nums text-white/60" :class="slot.rank === 1 ? 'text-sm short:text-xs' : 'text-xs short:text-[11px]'">{{ duration(slot.entry.minutes) }}</span>
                            </template>
                            <button
                                v-else
                                @click="invite"
                                class="group flex flex-col items-center min-w-0 max-w-full text-white/50 hover:text-white transition"
                            >
                                <span
                                    class="flex items-center justify-center rounded-full border-2 border-dashed border-white/25 group-hover:border-white/60 transition"
                                    :style="{ width: `${sizes.other}px`, height: `${sizes.other}px` }"
                                    aria-hidden="true"
                                >
                                    <i class="fas fa-user-plus text-xs"></i>
                                </span>
                                <span class="mt-2 short:mt-1 max-w-full truncate text-sm short:text-xs font-semibold">Free spot</span>
                                <span class="text-xs short:text-[11px]">Invite a friend</span>
                            </button>
                            <div
                                class="pedestal mt-2 short:mt-1 w-full flex items-center justify-center rounded-t-lg font-bold"
                                :class="[
                                    slot.entry ? (slot.rank === 1 ? 'bg-white/20 text-white' : 'bg-white/10 text-white') : 'border border-b-0 border-dashed border-white/20 text-white/30',
                                    `pedestal-${slot.rank - 1}`,
                                ]"
                            >
                                <span class="flex items-center gap-1.5">
                                    <i v-if="slot.entry" class="fas fa-trophy pedestal-trophy" :class="MEDALS[slot.rank - 1]" aria-hidden="true"></i>
                                    <span><span class="sr-only">Rank</span>{{ slot.rank }}</span>
                                </span>
                            </div>
                        </li>
                    </ol>

                    <ol v-if="rows.length" class="mt-3 short:mt-2 space-y-1 short:space-y-0.5" aria-label="Everyone else">
                        <li v-for="entry in rows" :key="entry.user.id" :class="rowClass(entry)">
                            <span class="w-7 shrink-0 text-right text-sm font-semibold tabular-nums text-white/60">{{ entry.rank }}</span>
                            <DiscordAvatar :user="entry.user" :size="short ? 24 : 28" />
                            <span class="flex-1 min-w-0 truncate text-sm">
                                {{ name(entry.user) }}<span v-if="entry.me" class="text-white/60"> (you)</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums">{{ duration(entry.minutes) }}</span>
                        </li>
                    </ol>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import DiscordAvatar from './DiscordAvatar.vue';
import { discordInstanceId, discordDisplayName, inviteToActivity } from '../discord.js';
import { useToast } from '../composables/toast.js';

// Rolling windows, so "today" means the same for members everywhere.
const PERIODS = { day: '24 hours', week: '7 days', all: 'All time' };
const EMPTY = { day: 'in the last 24 hours', week: 'in the last 7 days', all: 'yet' };

// The trophies are the one accent, gold, silver and bronze by rank: two
// first at equal time both get gold.
const MEDALS = ['text-amber-300', 'text-slate-300', 'text-orange-400'];
// The first in the middle, the second on its left, the third on its right.
const PODIUM_ORDER = [2, 1, 3];

// As the day log and the stats detail write durations: 1h 15m, 40m.
const duration = (minutes) => {
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m}m`;
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
};

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

        // Arrow keys move between periods, as in any set of tabs.
        const tabRefs = {};
        const onTabKey = (event) => {
            const keys = Object.keys(PERIODS);
            const index = keys.indexOf(period.value);
            const next = {
                ArrowRight: (index + 1) % keys.length,
                ArrowLeft: (index - 1 + keys.length) % keys.length,
                Home: 0,
                End: keys.length - 1,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            period.value = keys[next];
            tabRefs[keys[next]]?.focus();
        };

        // Three places, held or free. A free place takes the rank it stands for.
        const podium = computed(() => {
            const entries = board.value?.entries ?? [];
            return [0, 1, 2].map((place) => ({ place, entry: entries[place], rank: entries[place]?.rank ?? place + 1 }));
        });

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

        const invite = async () => {
            try {
                await inviteToActivity();
            } catch (error) {
                console.warn('Could not open the invite window.', error);
                useToast().error("Discord's invite window did not open. Invite friends from the call instead.");
            }
        };

        return {
            PERIODS, EMPTY, MEDALS, PODIUM_ORDER, modalRef, period, board, loading, failed,
            load, tabRefs, onTabKey, podium, rows, sizes, short, bodyRef, moreBelow, checkMoreBelow,
            rowClass, invite, duration, name: discordDisplayName,
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

/* As in the stats: Windows draws a wide grey scrollbar otherwise. */
.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.more-below {
    mask-image: linear-gradient(to bottom, black calc(100% - 2.5rem), transparent);
}
</style>
