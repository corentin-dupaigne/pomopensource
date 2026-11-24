<template>
    <!-- The month's name, its days, the day picked. Short frames move the
         month's name into the stats' tab row and put the day on one line,
         so the days stay square and as large as the frame allows. -->
    <div class="calendar flex flex-col items-center gap-3">
        <MonthNav class="month-nav self-stretch" :month="month" :minutes="monthMinutes" @shift="$emit('shift', $event)" />

        <!-- The more time on a day, the deeper its blue, as on GitHub. -->
        <div class="days grid grid-cols-7 gap-1.5 w-full" role="group" :aria-label="`${monthLabel}: time focused each day`">
            <!-- Which day of the week each column is: a month starts in the
                 column of its first day. -->
            <span v-for="(name, i) in weekdays" :key="`w${i}`" class="weekday text-center text-[10px] leading-3 text-white/50" aria-hidden="true">{{ name }}</span>
            <template v-for="(cell, i) in cells" :key="i">
                <span v-if="!cell" class="blank aspect-square" aria-hidden="true"></span>
                <button
                    v-else
                    type="button"
                    @click="selected = cell.date"
                    :disabled="cell.isFuture"
                    :aria-label="`${cell.long}: ${formatMinutes(cell.minutes)}`"
                    :aria-pressed="selected === cell.date"
                    class="day aspect-square rounded-lg text-[11px] font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
                    :class="[cell.isFuture ? 'future' : `level-${cell.level}`, { selected: selected === cell.date }]"
                >{{ cell.day }}</button>
            </template>
        </div>

        <!-- The day picked: its time, and what it went on. Its square's outline
             says which day it is. -->
        <p class="picked w-full text-sm text-center" aria-live="polite">
            <span v-if="!selected" class="text-white/70">No focus in {{ monthName }}</span>
            <template v-else-if="dayTotal">
                <strong class="text-white">{{ formatMinutes(dayTotal) }}</strong><span v-if="daySubjects" class="text-white/70">{{ daySubjects }}</span>
            </template>
            <span v-else class="text-white/70">{{ selected === today ? 'Nothing yet' : 'No focus' }}</span>
        </p>
    </div>
</template>

<script>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import MonthNav from './MonthNav.vue';
import { formatMinutes, intensityOf } from '../composables/duration.js';
import {
    getLocalSessions, getLocalProjects, computeCalendarData, computeDayLog, toLocalDateString,
} from '../composables/localStats.js';

export default {
    components: { MonthNav },
    props: {
        isAuthenticated: { type: [Boolean, Number], default: false },
        // The first day of the month shown: the stats window keeps it, as
        // short frames show its name in the tab row.
        month: { type: Date, required: true },
        // Local 'YYYY-MM-DD' to open on; today when null.
        date: { type: String, default: null },
    },
    emits: ['shift'],
    setup(props) {
        const today = toLocalDateString(new Date());
        const selected = ref(props.date ?? today);
        const days = ref([]); // { date: 'YYYY-MM-DD', minutes_focused }
        const sessions = ref([]); // the selected day's: { minutes_focused, project }

        const loadMonth = async () => {
            if (!props.isAuthenticated) {
                days.value = computeCalendarData(getLocalSessions());
                return;
            }
            const m = props.month;
            try {
                const { data } = await axios.get(`/user-stats/calendar/${m.getFullYear()}/${m.getMonth() + 1}?view=month`);
                days.value = data.calendar;
            } catch (error) {
                console.error('Error fetching the month:', error);
                days.value = [];
            }
        };
        // Moving to another month picks the day there is most to see: today
        // in this month, else its last day with focus, else none.
        const pickInMonth = () => {
            const m = props.month;
            const prefix = `${m.getFullYear()}-${String(m.getMonth() + 1).padStart(2, '0')}-`;
            if (today.startsWith(prefix)) { selected.value = today; return; }
            const focused = days.value
                .filter((day) => day.date.startsWith(prefix) && day.minutes_focused > 0)
                .map((day) => day.date)
                .sort();
            selected.value = focused.at(-1) ?? null;
        };
        // Opening keeps the day asked for; moving to another month picks one.
        watch(() => props.month, async (month, before) => {
            await loadMonth();
            if (before) pickInMonth();
        }, { immediate: true });

        const loadDay = async () => {
            if (!selected.value) {
                sessions.value = [];
                return;
            }
            if (!props.isAuthenticated) {
                sessions.value = computeDayLog(getLocalSessions(), getLocalProjects(), selected.value);
                return;
            }
            try {
                const { data } = await axios.get(`/user-stats/day/${selected.value}`);
                sessions.value = data.sessions;
            } catch (error) {
                console.error('Error fetching the day:', error);
                sessions.value = [];
            }
        };
        watch(selected, loadDay, { immediate: true });

        const minutesOn = (date) => days.value.find((day) => day.date === date)?.minutes_focused ?? 0;

        const cells = computed(() => {
            const first = props.month;
            const count = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
            // Weeks start on Monday, like the server's Carbon::startOfWeek().
            const list = Array((first.getDay() + 6) % 7).fill(null);
            for (let day = 1; day <= count; day++) {
                const date = new Date(first.getFullYear(), first.getMonth(), day);
                const key = toLocalDateString(date);
                const minutes = minutesOn(key);
                list.push({
                    day,
                    date: key,
                    minutes,
                    level: intensityOf(minutes),
                    isFuture: key > today,
                    long: date.toLocaleDateString('default', { weekday: 'long', day: 'numeric', month: 'long' }),
                });
            }
            // Always six weeks, so the window keeps its height from one
            // month to the next.
            while (list.length < 42) list.push(null);
            return list;
        });

        // Monday first, in the viewer's language.
        const weekdays = [...Array(7)].map((_, i) => new Date(2024, 0, 1 + i).toLocaleDateString('default', { weekday: 'narrow' }));

        const monthLabel = computed(() => props.month.toLocaleDateString('default', { month: 'long', year: 'numeric' }));
        const monthMinutes = computed(() => cells.value.reduce((sum, cell) => sum + (cell?.minutes ?? 0), 0));

        // In English, like the rest of the app; with its year when not this one's.
        const monthName = computed(() => props.month.toLocaleDateString('en-US', {
            month: 'long',
            ...(props.month.getFullYear() !== new Date().getFullYear() && { year: 'numeric' }),
        }));

        const dayTotal = computed(() => sessions.value.reduce((sum, s) => sum + s.minutes_focused, 0));

        // What the day's time went on, when that says more than its total,
        // with its lead-in (' on Maths', ' · Maths 50m · Essay 25m'):
        // time with no subject only counts in the total.
        const daySubjects = computed(() => {
            const named = new Map();
            for (const session of sessions.value) {
                if (!session.minutes_focused || !session.project?.name) continue;
                named.set(session.project.name, (named.get(session.project.name) ?? 0) + session.minutes_focused);
            }
            if (named.size === 0) return '';
            const [[onlyName, onlyMinutes]] = named;
            if (named.size === 1 && onlyMinutes === dayTotal.value) return ` on ${onlyName}`;
            return ' · ' + [...named]
                .sort((a, b) => b[1] - a[1])
                .map(([name, minutes]) => `${name} ${formatMinutes(minutes)}`)
                .join(' · ');
        });

        return { today, selected, cells, weekdays, monthLabel, monthMinutes, monthName, dayTotal, daySubjects, formatMinutes };
    },
};
</script>

<style scoped>
/* Deeper blue for more time; the day's number stays white on each. */
.day.level-0 { background: rgb(255 255 255 / 0.07); color: rgb(255 255 255 / 0.6); }
.day.level-1 { background: rgb(88 101 242 / 0.3); color: #fff; }
.day.level-2 { background: rgb(88 101 242 / 0.5); color: #fff; }
.day.level-3 { background: rgb(88 101 242 / 0.75); color: #fff; }
.day.level-4 { background: var(--discord-blurple); color: #fff; }
.day.future { background: transparent; color: rgb(255 255 255 / 0.3); box-shadow: inset 0 0 0 1px rgb(255 255 255 / 0.1); }
.day:not(:disabled):hover { filter: brightness(1.15); }
.day.selected { outline: 2px solid #fff; outline-offset: 2px; }

/* Short frames: the month's name is in the tab row, the day on one line,
   and the days square, a sixth of the height left once those are drawn. */
@media (max-height: 620px) {
    .calendar {
        --day: clamp(1.25rem, calc((100vh - 13.25rem) / 6 - 0.25rem), 2.75rem);
        gap: 0.375rem;
    }
    .month-nav { display: none; }
    .days {
        width: auto;
        grid-template-columns: repeat(7, minmax(0, var(--day)));
        gap: 0.25rem;
    }
    .day, .blank { aspect-ratio: auto; height: var(--day); }
    .picked { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
}
@media (max-height: 500px) {
    .calendar { --day: clamp(1.25rem, calc((100vh - 8.75rem) / 6 - 0.25rem), 2.75rem); }
}
</style>
