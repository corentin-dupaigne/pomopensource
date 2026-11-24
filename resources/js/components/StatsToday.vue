<template>
    <div v-if="isLoading" class="flex justify-center py-10">
        <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading stats"></i>
    </div>

    <!-- Today's time, the week it belongs to, and one line about both. -->
    <div v-else class="flex flex-col items-center gap-6 short:gap-3 text-center">
        <div class="flex flex-col items-center">
            <p class="font-display font-bold text-6xl short:text-5xl leading-none text-white tabular-nums">{{ formatMinutes(todayMinutes) }}</p>
            <p class="text-sm text-white/70">focused today</p>
        </div>

        <div class="flex items-end gap-3 h-24 short:h-20" role="group" aria-label="This week">
            <button
                v-for="day in week"
                :key="day.date"
                type="button"
                @click="$emit('openDay', day.date)"
                :disabled="day.isFuture"
                :aria-label="`${day.long}: ${formatMinutes(day.minutes)}`"
                class="group flex flex-col items-center justify-end gap-1.5 h-full w-7 rounded disabled:cursor-default focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70"
            >
                <span class="bar w-full rounded" :class="day.isToday ? 'today' : day.isFuture ? 'future' : 'past'" :style="{ height: `${day.height}px` }"></span>
                <span class="text-xs" :class="day.isToday ? 'font-bold text-white' : 'text-white/60'">{{ day.short }}</span>
            </button>
        </div>

        <p class="text-[15px] text-white/70">
            <strong class="text-white">{{ formatMinutes(weekMinutes) }}</strong> this week<template v-if="streak > 1"> · <strong class="text-white">{{ streak }} days</strong> in a row</template>
        </p>
    </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatMinutes } from '../composables/duration.js';
import { getLocalSessions, computeStats, computeCalendarData, toLocalDateString } from '../composables/localStats.js';

const BAR_HEIGHT = 66;

// Monday of the week of a date, like the server's Carbon::startOfWeek().
const weekStart = (date) => {
    const start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    start.setDate(start.getDate() - ((start.getDay() + 6) % 7));
    return start;
};

export default {
    props: {
        isAuthenticated: { type: [Boolean, Number], default: false },
    },
    emits: ['openDay'],
    setup(props) {
        const isLoading = ref(true);
        const days = ref([]); // { date: 'YYYY-MM-DD', minutes_focused }
        const streak = ref(0);
        const today = toLocalDateString(new Date());
        const monday = weekStart(new Date());

        const load = async () => {
            if (!props.isAuthenticated) {
                const sessions = getLocalSessions();
                days.value = computeCalendarData(sessions);
                streak.value = computeStats(sessions).day_streak;
                isLoading.value = false;
                return;
            }
            const week = `/user-stats/calendar/${monday.getFullYear()}/${monday.getMonth() + 1}/${monday.getDate()}?view=week`;
            const [summary, calendar] = await Promise.allSettled([axios.get('/user-stats'), axios.get(week)]);
            if (summary.status === 'fulfilled') streak.value = summary.value.data.stats.day_streak;
            if (calendar.status === 'fulfilled') days.value = calendar.value.data.calendar;
            [summary, calendar]
                .filter((result) => result.status === 'rejected')
                .forEach((result) => console.error('Error fetching stats:', result.reason));
            isLoading.value = false;
        };
        onMounted(load);

        const minutesOn = (date) => days.value.find((day) => day.date === date)?.minutes_focused ?? 0;

        const week = computed(() => {
            const list = [...Array(7)].map((_, i) => {
                const date = new Date(monday);
                date.setDate(date.getDate() + i);
                const key = toLocalDateString(date);
                return {
                    date: key,
                    minutes: minutesOn(key),
                    short: date.toLocaleDateString('default', { weekday: 'narrow' }),
                    long: date.toLocaleDateString('default', { weekday: 'long' }),
                    isToday: key === today,
                    isFuture: key > today,
                };
            });
            // Two hours fill the bar at least: one short day is not a full one.
            const top = Math.max(120, ...list.map((day) => day.minutes));
            return list.map((day) => ({ ...day, height: Math.max(3, Math.round(BAR_HEIGHT * day.minutes / top)) }));
        });

        const todayMinutes = computed(() => minutesOn(today));
        const weekMinutes = computed(() => week.value.reduce((sum, day) => sum + day.minutes, 0));

        return { isLoading, week, todayMinutes, weekMinutes, streak, formatMinutes };
    },
};
</script>

<style scoped>
/* The one accent of the stats is on the time itself: today full, the days
   before it lighter. */
.bar.today { background: var(--discord-blurple); }
.bar.past { background: rgb(88 101 242 / 0.45); }
.group:hover .bar.past { background: rgb(88 101 242 / 0.65); }
.bar.future { background: rgb(255 255 255 / 0.1); }
</style>
