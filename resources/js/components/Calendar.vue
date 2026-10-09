<template>
    <div class="calendar bg-white/10 backdrop-blur-lg rounded-lg p-6 shadow-lg">
        <!-- One row in short frames: period navigation, then the view tabs. -->
        <div class="short:flex short:items-center short:gap-4 short:mb-2">
        <div class="flex justify-between items-center mb-4 short:mb-0 short:flex-1">
            <button
                @click="previousPeriod"
                :aria-label="`Previous ${currentView}`"
                class="text-white hover:text-gray-300 transition"
            >
                <i class="fas fa-chevron-left" aria-hidden="true"></i>
            </button>
            <h3 class="text-lg font-semibold text-white">{{ currentPeriodLabel }}</h3>
            <button
                @click="nextPeriod"
                :aria-label="`Next ${currentView}`"
                class="text-white hover:text-gray-300 transition"
            >
                <i class="fas fa-chevron-right" aria-hidden="true"></i>
            </button>
        </div>

        <div class="flex space-x-2 mb-4 short:mb-0" role="tablist" aria-label="Calendar view">
            <button
                v-for="view in ['Week', 'Month', 'Year']"
                :key="view"
                :class="['px-4 py-2 short:px-3 short:py-1 rounded-lg text-sm font-semibold transition',
                    currentView === view.toLowerCase() ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                @click="changeView(view.toLowerCase())"
                role="tab"
                :aria-selected="currentView === view.toLowerCase()"
            >
                {{ view }}
            </button>
        </div>
        </div>

        <div v-if="currentView === 'week'" class="grid grid-cols-7 gap-1" id="weekly-view" role="grid" aria-label="Weekly calendar">
            <div v-for="day in daysOfWeek" :key="day" class="text-center text-sm font-medium text-white/70" role="columnheader">
                {{ day }}
            </div>
            <div
                v-for="(day, index) in weekCalendarDays"
                :key="index"
                class="day-cell aspect-square short:aspect-auto short:h-11 flex flex-col items-center justify-center text-sm rounded-lg p-2 transition hover:bg-white/10"
                :class="getDayClasses(day)"
                role="gridcell"
                :aria-label="day.date ? `${day.date.toLocaleDateString()}: ${day.minutesFocused} minutes focused` : ''"
                :aria-selected="isSelected(day)"
                :tabindex="day.date ? 0 : -1"
                @click="selectDay(day)"
                @keydown.enter.prevent="selectDay(day)"
                @keydown.space.prevent="selectDay(day)"
            >
                <span class="text-white">{{ day.date.getDate() }}</span>
                <span v-if="day.minutesFocused > 0" class="text-xs mt-1 text-white font-bold">
                    {{ formatTime(day.minutesFocused) }}
                </span>
            </div>
        </div>

        <div v-else-if="currentView === 'month'" class="grid grid-cols-7 gap-1" id="monthly-view" role="grid" aria-label="Monthly calendar">
            <div v-for="day in daysOfWeek" :key="day" class="text-center text-sm font-medium text-white/70" role="columnheader">
                {{ day }}
            </div>
            <div
                v-for="(day, index) in monthCalendarDays"
                :key="index"
                class="day-cell aspect-square short:aspect-auto short:h-11 flex flex-col items-center justify-center text-sm rounded-lg p-2 transition hover:bg-white/10"
                :class="getDayClasses(day)"
                role="gridcell"
                :aria-label="day.date ? `${day.date.toLocaleDateString()}: ${day.minutesFocused} minutes focused` : ''"
                :aria-selected="isSelected(day)"
                :tabindex="day.date ? 0 : -1"
                @click="selectDay(day)"
                @keydown.enter.prevent="selectDay(day)"
                @keydown.space.prevent="selectDay(day)"
            >
                <span v-if="day.date" class="text-white">{{ day.date.getDate() }}</span>
                <span v-if="day.minutesFocused > 0" class="text-xs mt-1 text-white font-bold">
                    {{ formatTime(day.minutesFocused) }}
                </span>
            </div>
        </div>

        <div v-else-if="currentView === 'year'" class="grid grid-cols-4 gap-4" id="yearly-view" aria-label="Yearly calendar">
            <div
                v-for="(month, index) in yearCalendarMonths"
                :key="index"
                :class="getMonthClasses(month)"
                :aria-label="`${month.name}: ${formatTime(month.minutesFocused)}`"
            >
                <span class="font-medium">{{ month.name }}</span>
                <span class="text-sm mt-1 text-white">{{ formatTime(month.minutesFocused) }}</span>
            </div>
        </div>

        <p class="mt-4 text-center text-white">
            Current streak: <span class="font-bold">{{ currentStreak }}</span> days
        </p>
    </div>
</template>

<script>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { parseLocalDate, toLocalDateString } from '../composables/localStats.js';

// Mid to dark blues: the day and its time are white, and lighter blues
// left them hard to read.
const DAY_INTENSITY_CLASSES = [
    'bg-blue-500/40',
    'bg-blue-500/60',
    'bg-blue-600/75',
    'bg-blue-700/90',
];

const MONTH_INTENSITY_CLASSES = [
    'bg-blue-500/40',
    'bg-blue-500/60',
    'bg-blue-600/75',
    'bg-blue-700/90',
];

export default {
    props: {
        localData: { type: Array, default: null },
        localStreak: { type: Number, default: null },
        // Local 'YYYY-MM-DD' of the day shown in the day log.
        selectedDate: { type: String, default: null },
    },
    emits: ['select-day'],
    setup(props, { emit }) {
        const currentDate = ref(new Date());
        const currentView = ref('month');
        const calendarData = ref([]);
        const currentStreak = ref(0);

        const daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        const currentPeriodLabel = computed(() => {
            if (currentView.value === 'week') {
                const weekStart = getWeekStart(currentDate.value);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);
                return `${weekStart.toLocaleDateString('default', { month: 'short', day: 'numeric' })} - ${weekEnd.toLocaleDateString('default', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            } else if (currentView.value === 'month') {
                return currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' });
            } else {
                return currentDate.value.getFullYear().toString();
            }
        });

        const weekCalendarDays = computed(() => {
            const weekStart = getWeekStart(currentDate.value);
            return [...Array(7)].map((_, i) => {
                const date = new Date(weekStart);
                date.setDate(date.getDate() + i);
                const dayData = calendarData.value.find(d => parseLocalDate(d.date).toDateString() === date.toDateString()) || {};
                return {
                    date,
                    hasSession: dayData.has_session || false,
                    minutesFocused: dayData.minutes_focused || 0
                };
            });
        });

        const monthCalendarDays = computed(() => {
            const year = currentDate.value.getFullYear();
            const month = currentDate.value.getMonth();
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            const daysInMonth = lastDay.getDate();

            let days = [];

            // Weeks start on Monday, like the server's Carbon::startOfWeek().
            const leadingBlanks = (firstDay.getDay() + 6) % 7;
            for (let i = 0; i < leadingBlanks; i++) {
                days.push({ date: null, hasSession: false, minutesFocused: 0 });
            }

            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(year, month, i);
                const dayData = calendarData.value.find(d => parseLocalDate(d.date).toDateString() === date.toDateString()) || {};
                days.push({
                    date,
                    hasSession: dayData.has_session || false,
                    minutesFocused: dayData.minutes_focused || 0
                });
            }

            return days;
        });

        const yearCalendarMonths = computed(() => {
            return [...Array(12)].map((_, i) => {
                const year = currentDate.value.getFullYear();
                const monthData = calendarData.value.filter(d => {
                    const date = parseLocalDate(d.date);
                    return date.getFullYear() === year && date.getMonth() === i;
                });
                return {
                    name: new Date(currentDate.value.getFullYear(), i, 1).toLocaleString('default', { month: 'short' }),
                    minutesFocused: monthData.reduce((sum, day) => sum + day.minutes_focused, 0)
                };
            });
        });

        const fetchCalendarData = async () => {
            try {
                let url;
                if (currentView.value === 'week') {
                    const weekStart = getWeekStart(currentDate.value);
                    url = `/user-stats/calendar/${weekStart.getFullYear()}/${weekStart.getMonth() + 1}/${weekStart.getDate()}?view=week`;
                } else if (currentView.value === 'month') {
                    url = `/user-stats/calendar/${currentDate.value.getFullYear()}/${currentDate.value.getMonth() + 1}?view=month`;
                } else {
                    url = `/user-stats/calendar/${currentDate.value.getFullYear()}?view=year`;
                }
                const response = await axios.get(url);
                calendarData.value = response.data.calendar;
                currentStreak.value = response.data.currentStreak;
            } catch (error) {
                console.error('Error fetching calendar data:', error);
            }
        };

        const previousPeriod = () => {
            if (currentView.value === 'week') {
                currentDate.value = new Date(currentDate.value.setDate(currentDate.value.getDate() - 7));
            } else if (currentView.value === 'month') {
                currentDate.value = new Date(currentDate.value.setMonth(currentDate.value.getMonth() - 1));
            } else {
                currentDate.value = new Date(currentDate.value.setFullYear(currentDate.value.getFullYear() - 1));
            }
        };

        const nextPeriod = () => {
            if (currentView.value === 'week') {
                currentDate.value = new Date(currentDate.value.setDate(currentDate.value.getDate() + 7));
            } else if (currentView.value === 'month') {
                currentDate.value = new Date(currentDate.value.setMonth(currentDate.value.getMonth() + 1));
            } else {
                currentDate.value = new Date(currentDate.value.setFullYear(currentDate.value.getFullYear() + 1));
            }
        };

        // Fetching is handled by the currentView watcher (accounts only).
        const changeView = (view) => {
            currentView.value = view;
        };

        const isSelected = (day) => Boolean(day.date) && toLocalDateString(day.date) === props.selectedDate;

        const selectDay = (day) => {
            if (day.date) emit('select-day', toLocalDateString(day.date));
        };

        const getDayClasses = (day) => {
            if (!day.date) return 'invisible';
            let classes = 'cursor-pointer hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/70';
            if (isSelected(day)) classes += ' ring-2 ring-white';

            if (day.hasSession) {
                const intensityIndex = Math.min(
                    Math.floor(day.minutesFocused / 60),
                    DAY_INTENSITY_CLASSES.length - 1
                );
                classes += ' ' + DAY_INTENSITY_CLASSES[intensityIndex];
            }
            return classes;
        };

        const getMonthClasses = (month) => {
            let classes = 'flex flex-col items-center justify-center rounded-lg p-4 text-white transition duration-200 ease-in-out';
            if (month.minutesFocused > 0) {
                const maxMinutesPerMonth = 30 * 90;
                const intensityIndex = Math.min(
                    Math.floor(month.minutesFocused / maxMinutesPerMonth * MONTH_INTENSITY_CLASSES.length),
                    MONTH_INTENSITY_CLASSES.length - 1
                );
                classes += ' ' + MONTH_INTENSITY_CLASSES[intensityIndex] + ' font-bold';
            } else {
                classes += ' bg-white/10';
            }
            return classes;
        };

        const formatTime = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours}h ${mins}m`;
        };

        const getWeekStart = (date) => {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        };

        onMounted(() => {
            if (props.localData !== null) {
                calendarData.value = props.localData;
                currentStreak.value = props.localStreak ?? 0;
            } else {
                fetchCalendarData();
            }
        });

        watch([currentDate, currentView], () => {
            if (props.localData === null) fetchCalendarData();
        });

        return {
            currentDate,
            currentView,
            currentPeriodLabel,
            daysOfWeek,
            weekCalendarDays,
            monthCalendarDays,
            yearCalendarMonths,
            currentStreak,
            previousPeriod,
            nextPeriod,
            changeView,
            isSelected,
            selectDay,
            getDayClasses,
            getMonthClasses,
            formatTime
        };
    }
};
</script>

<style scoped>
.calendar {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 0.75rem;
    backdrop-filter: blur(10px);
}

.day-cell {
    position: relative;
    cursor: pointer;
}

/* Short frames: slim rows, the time beside the day number. */
@media (max-height: 500px) {
    .calendar {
        padding: 0.75rem;
    }

    .day-cell {
        flex-direction: row;
        gap: 0.375rem;
        padding: 0.25rem;
    }

    .day-cell span:first-child {
        font-size: 1rem;
    }

    .day-cell span:last-child {
        margin-top: 0;
        font-size: 0.75rem;
    }
}

.day-cell span:first-child {
    font-size: 1.25rem;
    font-weight: bold;
    color: #fff;
}

.day-cell span:last-child {
    font-size: 0.875rem;
    font-weight: bold;
    color: rgba(255, 255, 255, 0.9);
}

button {
    outline: none;
}
</style>
