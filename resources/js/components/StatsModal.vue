<template>
    <div
        class="safe-area fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="stats-title"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content modal-surface flex flex-col w-full max-w-2xl mx-4 p-4 sm:p-6 short:p-3 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl transform transition-all duration-300 ease-in-out focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <!-- Title, tabs and close share one row: short frames need the height. -->
            <div class="shrink-0 flex items-center gap-4 pb-4 short:pb-2 border-b border-white/20">
                <h2 id="stats-title" class="text-2xl short:text-xl font-bold font-oswald text-white">Report</h2>
                <div class="flex gap-2 mr-auto" role="tablist" aria-label="Report sections">
                    <button
                        v-for="tab in ['Summary', 'Detail']"
                        :key="tab"
                        :class="['px-3 py-1.5 rounded-lg text-sm font-semibold transition',
                                 activeTab === tab ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                        @click="activeTab = tab"
                        role="tab"
                        :aria-selected="activeTab === tab"
                        :aria-controls="`tab-panel-${tab.toLowerCase()}`"
                    >
                        {{ tab }}
                    </button>
                </div>
                <button
                    @click="$emit('close')"
                    aria-label="Close report"
                    class="text-white hover:text-gray-300 transition duration-150 ease-in-out"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body flex-1 min-h-0 pt-6 short:pt-3 overflow-y-auto">

                <div
                    v-if="activeTab === 'Summary'"
                    id="tab-panel-summary"
                    role="tabpanel"
                >

                    <div v-if="isLoadingStats" class="flex justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading stats"></i>
                    </div>
                    <ActivitySummary v-else :stats="stats" class="short:mb-3" />

                    <h3 class="text-lg font-semibold font-oswald text-white mt-6 mb-4 short:sr-only">Monthly Activity</h3>
                    <Calendar :localData="localCalendarData" :localStreak="localStreak" />
                </div>

                <div
                    v-else
                    id="tab-panel-detail"
                    role="tabpanel"
                >
                    <h3 class="text-lg font-semibold font-oswald text-white mb-4 short:sr-only">Detailed Activity</h3>
                    <ActivityDetail :localProjectsData="localProjectStats" />
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, watch, nextTick } from 'vue';
import axios from 'axios';
import ActivitySummary from './ActivitySummary.vue';
import Calendar from './Calendar.vue';
import ActivityDetail from './ActivityDetail.vue';
import { getLocalSessions, computeStats, computeCalendarData, computeProjectStats } from '../composables/localStats.js';

export default {
    components: {
        ActivityDetail,
        ActivitySummary,
        Calendar,
    },
    emits: ['close'],
    props: {
        isAuthenticated: { type: [Boolean, Number], default: false },
    },
    setup(props) {
        const activeTab = ref('Summary');
        const modalRef = ref(null);
        const isLoadingStats = ref(true);
        const stats = ref({
            hours_focused: 0,
            days_accessed: 0,
            day_streak: 0,
        });
        const localCalendarData = ref(null);
        const localStreak = ref(0);
        const localProjectStats = ref(null);

        const fetchStats = async () => {
            isLoadingStats.value = true;
            try {
                const response = await axios.get('/user-stats');
                stats.value = response.data.stats;
            } catch (error) {
                console.error('Error fetching stats:', error);
            } finally {
                isLoadingStats.value = false;
            }
        };

        const loadLocalStats = () => {
            const sessions = getLocalSessions();
            const computed = computeStats(sessions);
            stats.value = computed;
            localCalendarData.value = computeCalendarData(sessions);
            localStreak.value = computed.day_streak;
            const storedProjects = (() => {
                try { return JSON.parse(localStorage.getItem('localProjects') || '[]'); }
                catch { return []; }
            })();
            localProjectStats.value = computeProjectStats(sessions, storedProjects);
            isLoadingStats.value = false;
        };

        // Guest data must be ready before the child Calendar and ActivityDetail
        // mount (children mount first), or they fall back to the account API.
        if (!props.isAuthenticated) loadLocalStats();

        onMounted(async () => {
            if (props.isAuthenticated) await fetchStats();
            await nextTick();
            modalRef.value?.focus();
        });

        return {
            activeTab,
            modalRef,
            isLoadingStats,
            stats,
            localCalendarData,
            localStreak,
            localProjectStats,
        };
    },
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    min-height: min(500px, calc(100% - 2rem));
    max-height: min(90vh, calc(100% - 2rem));
    overflow: hidden;
}

.modal-body {
    overflow-y: auto;
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
