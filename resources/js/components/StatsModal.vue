<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="stats-title"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content w-full max-w-2xl p-6 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl transform transition-all duration-300 ease-in-out focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <div class="flex justify-between items-center pb-4 border-b border-white/20">
                <h2 id="stats-title" class="text-2xl font-bold font-oswald text-white">Report</h2>
                <button
                    @click="$emit('close')"
                    aria-label="Close report"
                    class="text-white hover:text-gray-300 transition duration-150 ease-in-out"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
            <div class="modal-body pt-6 overflow-y-auto">
                <div class="flex space-x-2 mb-6" role="tablist" aria-label="Report sections">
                    <button
                        v-for="tab in ['Summary', 'Detail']"
                        :key="tab"
                        :class="['px-4 py-2 rounded-lg text-sm font-semibold transition',
                                 activeTab === tab ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                        @click="activeTab = tab"
                        role="tab"
                        :aria-selected="activeTab === tab"
                        :aria-controls="`tab-panel-${tab.toLowerCase()}`"
                    >
                        {{ tab }}
                    </button>
                </div>

                <div
                    v-if="activeTab === 'Summary'"
                    id="tab-panel-summary"
                    role="tabpanel"
                >
                    <h3 class="text-lg font-semibold font-oswald text-white mb-4">Activity Summary</h3>

                    <div v-if="isLoadingStats" class="flex justify-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading stats"></i>
                    </div>
                    <ActivitySummary v-else :stats="stats" />

                    <h3 class="text-lg font-semibold font-oswald text-white mt-8 mb-4">Monthly Activity</h3>
                    <Calendar />
                </div>

                <div
                    v-else
                    id="tab-panel-detail"
                    role="tabpanel"
                >
                    <h3 class="text-lg font-semibold font-oswald text-white mb-4">Detailed Activity</h3>
                    <ActivityDetail />
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

export default {
    components: {
        ActivityDetail,
        ActivitySummary,
        Calendar,
    },
    emits: ['close'],
    setup() {
        const activeTab = ref('Summary');
        const modalRef = ref(null);
        const isLoadingStats = ref(true);
        const stats = ref({
            hours_focused: 0,
            days_accessed: 0,
            day_streak: 0,
        });

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

        onMounted(async () => {
            await fetchStats();
            await nextTick();
            modalRef.value?.focus();
        });

        return {
            activeTab,
            modalRef,
            isLoadingStats,
            stats,
        };
    },
};
</script>

<style scoped>
.modal-content {
    max-height: 90vh;
    overflow: hidden;
}

.modal-body {
    max-height: calc(90vh - 5rem);
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
