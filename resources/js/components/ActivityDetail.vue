<template>
    <div class="activity-detail bg-white/10 backdrop-blur-lg rounded-lg p-6 shadow-lg">
        <div v-if="isLoading" class="flex justify-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading activity"></i>
        </div>
        <div v-else-if="projects.length">
            <div v-for="project in projects" :key="project.id" class="mb-6">
                <h3 class="text-xl font-semibold text-white mb-3">
                    {{ project.name }}
                    <span class="text-base font-normal text-white/60 ml-2">
                        {{ formatHours(project.total_time_focused) }}
                    </span>
                </h3>
                <div class="ml-4 space-y-1">
                    <div v-for="task in project.tasks" :key="task.id" class="flex items-center gap-2 text-white/70">
                        <i class="fas fa-circle text-white/30 text-[8px]" aria-hidden="true"></i>
                        <span>{{ task.name }}</span>
                        <span class="text-white/40 text-sm">— {{ formatHours(task.time_focused) }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div v-else class="text-white/50 text-sm font-inter text-center py-4">
            No focus sessions recorded yet.
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import axios from 'axios';

export default {
    setup() {
        const projects = ref([]);
        const isLoading = ref(true);

        const formatHours = (seconds) => {
            if (!seconds) return '0m';
            const totalMinutes = Math.round(seconds / 60);
            const h = Math.floor(totalMinutes / 60);
            const m = totalMinutes % 60;
            if (h === 0) return `${m}m`;
            return m === 0 ? `${h}h` : `${h}h ${m}m`;
        };

        const fetchProjectStats = async () => {
            isLoading.value = true;
            try {
                const response = await axios.get('/projects-stats');
                projects.value = response.data.projects;
            } catch (error) {
                console.error('Error fetching project stats:', error);
            } finally {
                isLoading.value = false;
            }
        };

        onMounted(fetchProjectStats);

        return { projects, isLoading, formatHours };
    },
};
</script>

<style scoped>
.activity-detail {
    background-color: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 0.75rem;
    backdrop-filter: blur(10px);
}
</style>
