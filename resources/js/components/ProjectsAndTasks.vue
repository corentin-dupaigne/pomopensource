<template>
    <ConfirmModal
        :visible="confirmDialog.visible"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirmLabel="confirmDialog.confirmLabel"
        @confirm="handleConfirm"
        @cancel="handleCancel"
    />

    <Timer :projects="localProjects" :settings="settings" :isAuthenticated="isAuthenticated"/>

    <div class="w-full max-w-3xl px-6 bg-white/10 rounded-lg shadow-lg" :class="localProjects.length === 0 ? 'py-5' : 'py-8'">
        <h2 class="text-3xl font-bold mb-6 font-oswald text-white">Projects</h2>

        <!-- Auth prompt -->
        <div v-if="!isAuthenticated" class="mb-5 flex items-center gap-3 px-4 py-3 bg-yellow-500/20 border border-yellow-400/30 rounded-lg text-white">
            <i class="fas fa-info-circle text-yellow-300 shrink-0" aria-hidden="true"></i>
            <span class="text-sm font-inter">
                <a href="/register" class="font-semibold underline hover:text-yellow-200">Sign in</a>
                to save projects and track focus sessions.
            </span>
        </div>

        <!-- Add project form -->
        <form @submit.prevent="addProject" class="mb-6">
            <div class="mb-4">
                <input
                    v-model="newProjectName"
                    type="text"
                    placeholder="New project name"
                    required
                    :disabled="!isAuthenticated"
                    aria-label="New project name"
                    class="w-full py-3 px-4 bg-white/10 border border-white/30 rounded-lg text-center text-sm font-inter font-semibold text-white placeholder-white/70 focus:outline-none focus:border-white focus:ring-1 focus:ring-white transition duration-200 disabled:opacity-40 disabled:cursor-not-allowed"
                >
            </div>
            <button
                type="submit"
                :disabled="!isAuthenticated || isAddingProject"
                aria-label="Add new project"
                class="w-full py-3 border-2 border-dashed border-white rounded-lg text-center text-sm font-inter font-semibold text-white hover:bg-white/20 transition duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <i v-if="isAddingProject" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span>{{ isAddingProject ? 'Adding...' : '+ Add Project' }}</span>
            </button>
        </form>

        <!-- Projects list -->
        <ul class="space-y-4" aria-label="Projects">
            <li v-for="project in localProjects" :key="project.id" class="bg-white/5 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2 gap-2">
                    <!-- Editable project name -->
                    <div class="flex-1 group relative flex items-center gap-2 min-w-0">
                        <input
                            v-model="project.name"
                            @blur="updateProject(project)"
                            :aria-label="`Project name: ${project.name}`"
                            class="flex-1 bg-transparent border-b border-white/30 py-1 px-2 text-white font-inter font-semibold focus:outline-none focus:border-white hover:border-white/60 transition-colors min-w-0"
                        >
                        <i class="fas fa-pencil-alt text-white/30 text-xs group-hover:text-white/60 transition-colors shrink-0" aria-hidden="true"></i>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            @click="toggleTasksVisibility(project)"
                            class="text-white/70 hover:text-white transition text-sm font-inter whitespace-nowrap"
                            :aria-label="project.showTasks ? `Hide tasks for ${project.name}` : `Show tasks for ${project.name}`"
                            :aria-expanded="project.showTasks"
                        >
                            <i :class="project.showTasks ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="mr-1" aria-hidden="true"></i>
                            {{ project.showTasks ? 'Hide tasks' : 'Show tasks' }}
                        </button>
                        <button
                            @click="openDeleteProject(project.id, project.name)"
                            class="text-red-400 hover:text-red-500 transition"
                            :aria-label="`Delete project ${project.name}`"
                        >
                            <i class="fas fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <!-- Tasks section -->
                <div v-if="project.showTasks" class="mt-4">
                    <form @submit.prevent="addTask(project)" class="mb-4">
                        <div class="flex">
                            <input
                                v-model="newTaskNames[project.id]"
                                type="text"
                                placeholder="New task name"
                                required
                                :aria-label="`New task for ${project.name}`"
                                class="flex-grow py-2 px-3 bg-white/10 border border-white/30 rounded-l-lg text-sm font-inter font-semibold text-white placeholder-white/70 focus:outline-none focus:border-white focus:ring-1 focus:ring-white transition duration-200"
                            >
                            <button
                                type="submit"
                                :disabled="isAddingTask[project.id]"
                                :aria-label="`Add task to ${project.name}`"
                                class="py-2 px-4 bg-white/20 border border-white/30 rounded-r-lg text-sm font-inter font-semibold text-white hover:bg-white/30 transition duration-200 disabled:opacity-50 flex items-center gap-1"
                            >
                                <i v-if="isAddingTask[project.id]" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                                <span>{{ isAddingTask[project.id] ? '' : 'Add task' }}</span>
                            </button>
                        </div>
                    </form>

                    <p v-if="project.tasks && project.tasks.length === 0" class="text-white/40 text-sm font-inter text-center py-2">
                        No tasks yet.
                    </p>

                    <ul class="space-y-2" :aria-label="`Tasks for ${project.name}`">
                        <li
                            v-for="task in project.tasks"
                            :key="task.id"
                            class="flex items-center justify-between bg-white/5 rounded p-2 group"
                        >
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <input
                                    v-model="task.name"
                                    @blur="updateTask(project, task)"
                                    :aria-label="`Task name: ${task.name}`"
                                    class="flex-1 bg-transparent text-white font-inter focus:outline-none border-b border-transparent hover:border-white/30 focus:border-white transition-colors min-w-0"
                                >
                                <i class="fas fa-pencil-alt text-white/20 text-xs group-hover:text-white/50 transition-colors shrink-0" aria-hidden="true"></i>
                            </div>
                            <button
                                @click="openDeleteTask(project, task.id, task.name)"
                                class="text-red-400 hover:text-red-500 transition ml-2 shrink-0"
                                :aria-label="`Delete task ${task.name}`"
                            >
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</template>

<script>
import { ref, reactive } from 'vue';
import axios from 'axios';
import Timer from './Timer.vue';
import ConfirmModal from './ConfirmModal.vue';
import { useToast } from '../composables/toast.js';

export default {
    components: { Timer, ConfirmModal },
    props: {
        projects: {
            type: Array,
            required: true
        },
        settings: {
            type: Object,
        },
        isAuthenticated: {
            type: [Number, Boolean],
            default: false
        }
    },
    setup(props) {
        const { success, error } = useToast();
        const localProjects = ref([...props.projects]);
        const newProjectName = ref('');
        const newTaskNames = reactive({});
        const isAddingProject = ref(false);
        const isAddingTask = reactive({});

        const confirmDialog = reactive({
            visible: false,
            title: '',
            message: '',
            confirmLabel: 'Delete',
            onConfirm: null
        });

        const showConfirm = (title, message, onConfirm) => {
            confirmDialog.title = title;
            confirmDialog.message = message;
            confirmDialog.onConfirm = onConfirm;
            confirmDialog.visible = true;
        };

        const handleConfirm = async () => {
            confirmDialog.visible = false;
            if (confirmDialog.onConfirm) await confirmDialog.onConfirm();
        };

        const handleCancel = () => {
            confirmDialog.visible = false;
        };

        const addProject = async () => {
            if (!newProjectName.value.trim()) return;
            isAddingProject.value = true;
            try {
                const response = await axios.post('/projects', { name: newProjectName.value });
                localProjects.value.push({
                    ...response.data,
                    tasks: response.data.tasks ?? [],
                    showTasks: false
                });
                newProjectName.value = '';
                success('Project added');
            } catch (err) {
                error('Failed to add project');
            } finally {
                isAddingProject.value = false;
            }
        };

        const updateProject = async (project) => {
            try {
                await axios.patch(`/projects/${project.id}`, { name: project.name });
            } catch (err) {
                error('Failed to update project');
            }
        };

        const openDeleteProject = (projectId, projectName) => {
            showConfirm(
                'Delete project',
                `Delete "${projectName}" and all its tasks? This cannot be undone.`,
                () => deleteProject(projectId)
            );
        };

        const deleteProject = async (projectId) => {
            try {
                await axios.delete(`/projects/${projectId}`);
                localProjects.value = localProjects.value.filter(p => p.id !== projectId);
                success('Project deleted');
            } catch (err) {
                error('Failed to delete project');
            }
        };

        const toggleTasksVisibility = (project) => {
            project.showTasks = !project.showTasks;
        };

        const addTask = async (project) => {
            const taskName = newTaskNames[project.id]?.trim();
            if (!taskName) return;
            isAddingTask[project.id] = true;
            try {
                const response = await axios.post(`/projects/${project.id}/tasks`, { name: taskName });
                project.tasks.push(response.data);
                newTaskNames[project.id] = '';
                success('Task added');
            } catch (err) {
                error('Failed to add task');
            } finally {
                isAddingTask[project.id] = false;
            }
        };

        const updateTask = async (project, task) => {
            try {
                await axios.patch(`/tasks/${task.id}`, { name: task.name });
            } catch (err) {
                error('Failed to update task');
            }
        };

        const openDeleteTask = (project, taskId, taskName) => {
            showConfirm(
                'Delete task',
                `Delete "${taskName}"? This cannot be undone.`,
                () => deleteTask(project, taskId)
            );
        };

        const deleteTask = async (project, taskId) => {
            try {
                await axios.delete(`/tasks/${taskId}`);
                project.tasks = project.tasks.filter(t => t.id !== taskId);
                success('Task deleted');
            } catch (err) {
                error('Failed to delete task');
            }
        };

        return {
            localProjects,
            newProjectName,
            newTaskNames,
            isAddingProject,
            isAddingTask,
            confirmDialog,
            handleConfirm,
            handleCancel,
            addProject,
            updateProject,
            openDeleteProject,
            toggleTasksVisibility,
            addTask,
            updateTask,
            openDeleteTask,
        };
    }
};
</script>
