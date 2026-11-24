<template>
    <div
        class="safe-area fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-label="Stats"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content modal-surface flex flex-col w-full max-w-[25rem] mx-4 p-4 sm:p-6 sm:pt-4 short:p-3 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <!-- Tabs and close share one row, as in the leaderboard. -->
            <div class="shrink-0 flex items-center gap-2 pb-3 short:pb-2 border-b border-white/20">
                <div class="flex gap-2 mr-auto" role="tablist" aria-label="Stats" @keydown="onTabKey">
                    <button
                        v-for="(label, key) in TABS"
                        :key="key"
                        :ref="(el) => { if (el) tabRefs[key] = el; }"
                        @click="selectTab(key)"
                        role="tab"
                        :id="`stats-tab-${key}`"
                        :aria-selected="tab === key"
                        :tabindex="tab === key ? 0 : -1"
                        aria-controls="stats-panel"
                        :class="['px-3 py-1.5 rounded-lg text-sm font-semibold transition',
                                 tab === key ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                    >
                        {{ label }}
                    </button>
                </div>
                <!-- Short frames: the calendar's month here, to leave its
                     days the height. -->
                <MonthNav
                    v-if="tab === 'calendar'"
                    class="header-month flex-1"
                    compact
                    :month="month"
                    @shift="shiftMonth"
                />
                <button
                    @click="$emit('close')"
                    aria-label="Close stats"
                    class="w-11 h-11 -mr-3 short:w-9 short:h-9 short:-mr-2 flex items-center justify-center rounded text-white/80 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div
                id="stats-panel"
                role="tabpanel"
                :aria-labelledby="`stats-tab-${tab}`"
                class="modal-body flex-auto min-h-0 pt-5 pb-1 short:pt-2"
            >
                <StatsCalendar v-if="tab === 'calendar'" :isAuthenticated="isAuthenticated" :date="calendarDate" :month="month" @shift="shiftMonth" />
                <StatsToday v-else :isAuthenticated="isAuthenticated" @openDay="openDay" />
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue';
import { countClick } from '../composables/clicks.js';
import StatsToday from './StatsToday.vue';
import StatsCalendar from './StatsCalendar.vue';
import MonthNav from './MonthNav.vue';
import { parseLocalDate } from '../composables/localStats.js';
import { foldLocalTasks, foldLocalNotes } from '../composables/localStats.js';

const TABS = { today: 'Today', calendar: 'Calendar' };

export default {
    components: { StatsToday, StatsCalendar, MonthNav },
    emits: ['close'],
    props: {
        isAuthenticated: { type: [Boolean, Number], default: false },
    },
    setup(props) {
        const modalRef = ref(null);
        const tab = ref('today');
        const tabRefs = {};
        // The day the calendar opens on: null for today. And the month it
        // shows, kept here: short frames show it in the tab row.
        const calendarDate = ref(null);
        const firstOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1);
        const month = ref(firstOfMonth(new Date()));
        const shiftMonth = (step) => {
            const next = new Date(month.value.getFullYear(), month.value.getMonth() + step, 1);
            if (next > new Date()) return;
            month.value = next;
        };

        // Guest data from older pages is folded into sessions before it is read.
        if (!props.isAuthenticated) {
            foldLocalTasks();
            foldLocalNotes();
        }

        onMounted(async () => {
            await nextTick();
            modalRef.value?.focus();
        });

        // Whether the calendar is worth its tab: count who opens it. It
        // opens on today, or on the day picked under today's time.
        const selectTab = (key, date = null) => {
            if (key === 'calendar') {
                if (tab.value !== 'calendar') countClick('stats_calendar');
                calendarDate.value = date;
                month.value = firstOfMonth(date ? parseLocalDate(date) : new Date());
            }
            tab.value = key;
        };
        const openDay = (date) => selectTab('calendar', date);

        const onTabKey = (event) => {
            const keys = Object.keys(TABS);
            const index = keys.indexOf(tab.value);
            const next = {
                ArrowRight: (index + 1) % keys.length,
                ArrowLeft: (index - 1 + keys.length) % keys.length,
                Home: 0,
                End: keys.length - 1,
            }[event.key];
            if (next === undefined) return;
            event.preventDefault();
            selectTab(keys[next]);
            tabRefs[keys[next]]?.focus();
        };

        return { TABS, tab, tabRefs, modalRef, calendarDate, month, shiftMonth, selectTab, openDay, onTabKey };
    },
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    max-height: calc(100% - 2rem);
    overflow: hidden;
}

@media (max-height: 500px) {
    .modal-content { max-height: calc(100% - 1rem); }
}

/* The calendar's month in the tab row only in short frames. */
.header-month { display: none; }
@media (max-height: 620px) {
    .header-month { display: flex; }
}

/* A little room on each side, so the outline of a picked day is never cut
   off, and never scrolls sideways (Firefox counts it as overflow). */
.modal-body {
    overflow-x: hidden;
    overflow-y: auto;
    margin-inline: -6px;
    padding-inline: 6px;
}

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
</style>
