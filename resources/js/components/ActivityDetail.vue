<template>
    <div class="activity-detail bg-white/10 backdrop-blur-lg rounded-lg p-6 shadow-lg">
        <div v-if="isLoading" class="flex justify-center py-8">
            <i class="fas fa-spinner fa-spin text-2xl text-white/40" aria-label="Loading activity"></i>
        </div>
        <div v-else-if="projects.length">
            <div v-for="project in projects" :key="project.id" class="flex items-baseline justify-between gap-4 py-2">
                <h3 class="text-xl font-semibold text-white truncate">{{ project.name }}</h3>
                <span class="text-base text-white/60 shrink-0">{{ formatHours(project.total_time_focused) }}</span>
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
    props: {
        localProjectsData: { type: Array, default: null },
    },
    setup(props) {
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

        onMounted(() => {
            if (props.localProjectsData !== null) {
                projects.value = props.localProjectsData;
                isLoading.value = false;
            } else {
                fetchProjectStats();
            }
        });

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
