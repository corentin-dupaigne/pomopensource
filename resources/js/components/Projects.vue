<template>
    <ConfirmModal
        :visible="confirmDialog.visible"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirmLabel="confirmDialog.confirmLabel"
        @confirm="handleConfirm"
        @cancel="handleCancel"
    />

    <Timer
        :projects="localProjects"
        :settings="settings"
        :isAuthenticated="isAuthenticated"
        :zenMode="zenMode"
        :createProject="createProject"
        @manageProjects="$emit('openPanel')"
    />

    <!-- Managing projects is occasional: it opens as a sheet, from the header or
         from the picker, instead of sitting under the timer. -->
    <teleport to="body">
    <div
        v-if="panelOpen"
        class="safe-area fixed inset-0 z-50 bg-black/70"
        role="dialog"
        aria-modal="true"
        aria-label="Projects"
        @keydown.esc="$emit('closePanel')"
    >
    <!-- A sheet from the bottom on a phone, from the right otherwise. -->
    <div class="w-full h-full flex items-end justify-center sm:justify-end sm:items-stretch" @click.self="$emit('closePanel')">
    <div class="w-full max-h-[85%] sm:max-h-full sm:max-w-md px-4 py-5 overflow-y-auto bg-neutral-900/80 backdrop-blur-lg text-white rounded-t-2xl sm:rounded-none shadow-lg">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-2xl font-bold font-oswald text-white">Projects</h2>
            <div class="flex items-center gap-4">
                <!-- Renaming and deleting stay out of the way until asked for. -->
                <button
                    v-if="localProjects.length > 0"
                    @click="editing = !editing"
                    :aria-pressed="editing"
                    class="px-3 py-1.5 rounded-full text-sm font-inter font-semibold transition"
                    :class="editing ? 'bg-white text-black' : 'bg-white/10 text-white hover:bg-white/20'"
                >
                    {{ editing ? 'done' : 'edit' }}
                </button>
                <span v-if="!isAuthenticated" class="text-xs text-white/35 font-inter">
                    saved locally<template v-if="!isDiscordActivity"> ·
                    <a href="/login" class="hover:text-white/60 transition underline">sign in to sync</a></template>
                </span>
                <button
                    ref="closePanelRef"
                    @click="$emit('closePanel')"
                    aria-label="Close projects"
                    class="w-11 h-11 -mr-2 flex items-center justify-center text-white hover:text-gray-300 transition"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Add project form: a single row in the Activity panel -->
        <form @submit.prevent="addProject" class="flex gap-2 mb-4">
            <input
                v-model="newProjectName"
                type="text"
                placeholder="New project"
                required
                aria-label="New project name"
                class="flex-1 min-w-0 py-2.5 px-3 bg-white/10 border border-white/30 rounded-lg text-sm font-inter text-white placeholder-white/60 focus:outline-none focus:border-white focus:ring-1 focus:ring-white transition"
            >
            <button
                type="submit"
                :disabled="isAddingProject"
                aria-label="Add project"
                class="shrink-0 min-w-11 px-4 bg-white text-black rounded-lg text-sm font-inter font-semibold disabled:opacity-40 flex items-center justify-center"
            >
                <i class="fas" :class="isAddingProject ? 'fa-spinner fa-spin' : 'fa-plus'" aria-hidden="true"></i>
            </button>
        </form>

        <p v-if="localProjects.length === 0" class="text-white/50 text-sm font-inter text-center py-4">
            Group your focus sessions by project, then pick one under the timer.
        </p>


        <!-- Projects list -->
        <ul class="space-y-4" aria-label="Projects">
            <li v-for="project in localProjects" :key="project.id" class="group flex items-center gap-2 bg-white/5 rounded-lg p-4">
                <span v-if="!editable" class="flex-1 min-w-0 truncate py-1 font-inter font-semibold text-white">{{ project.name }}</span>
                <template v-else>
                    <input
                        v-model="project.name"
                        @keydown.enter.prevent="$event.target.blur()"
                        @keydown.esc.stop="cancelRename(project, $event)"
                        @focus="renaming[project.id] = project.name"
                        @blur="updateProject(project)"
                        :aria-label="`Project name: ${project.name}`"
                        maxlength="255"
                        class="flex-1 min-w-0 bg-transparent border-b border-white/30 py-1 px-2 text-white font-inter font-semibold focus:outline-none focus:border-white hover:border-white/60 transition-colors"
                    >
                    <i class="fas fa-pencil-alt text-white/30 text-xs group-hover:text-white/60 transition-colors shrink-0" aria-hidden="true"></i>
                    <button
                        @click="openDeleteProject(project.id, project.name)"
                        class="w-8 h-8 shrink-0 flex items-center justify-center text-red-400 hover:text-red-500 transition"
                        :aria-label="`Delete project ${project.name}`"
                    >
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                </template>
            </li>
        </ul>
    </div>
    </div>
    </div>
    </teleport>
</template>

<script>
import { ref, reactive, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import Timer from './Timer.vue';
import ConfirmModal from './ConfirmModal.vue';
import { useToast } from '../composables/toast.js';
import { isDiscordActivity } from '../discord.js';
import { foldLocalTasks } from '../composables/localStats.js';

const LOCAL_KEY = 'localProjects';
let localIdCounter = Date.now();
const localId = () => `local_${localIdCounter++}`;

export default {
    components: { Timer, ConfirmModal },
    props: {
        projects: { type: Array, required: true },
        settings: { type: Object },
        isAuthenticated: { type: [Number, Boolean], default: false },
        zenMode: { type: Boolean, default: false },
        panelOpen: { type: Boolean, default: false },
    },
    emits: ['closePanel', 'openPanel'],
    setup(props) {
        const { success, error } = useToast();

        const loadLocal = () => {
            try { return JSON.parse(localStorage.getItem(LOCAL_KEY) || '[]'); }
            catch { return []; }
        };

        if (!props.isAuthenticated) foldLocalTasks();
        const localProjects = ref(
            props.isAuthenticated ? [...props.projects] : loadLocal()
        );

        const persistLocal = () => {
            if (!props.isAuthenticated) {
                localStorage.setItem(LOCAL_KEY, JSON.stringify(localProjects.value));
            }
        };

        // Names are plain text until the user asks to edit.
        const editing = ref(false);
        const editable = computed(() => editing.value);

        const closePanelRef = ref(null);
        watch(() => props.panelOpen, async (open) => {
            if (!open) {
                editing.value = false;
                return;
            }
            await nextTick();
            closePanelRef.value?.focus();
        });

        const newProjectName = ref('');
        const isAddingProject = ref(false);
        // Name before editing started, to put back on Escape or when cleared.
        const renaming = reactive({});

        const confirmDialog = reactive({
            visible: false, title: '', message: '', confirmLabel: 'Delete', onConfirm: null
        });

        const showConfirm = (title, message, onConfirm) => {
            Object.assign(confirmDialog, { title, message, onConfirm, visible: true });
        };

        const handleConfirm = async () => {
            confirmDialog.visible = false;
            if (confirmDialog.onConfirm) await confirmDialog.onConfirm();
        };

        const handleCancel = () => { confirmDialog.visible = false; };

        // Shared by the panel and the picker under the timer.
        const createProject = async (rawName) => {
            const name = rawName.trim();
            if (!name) return null;
            try {
                const project = props.isAuthenticated
                    ? (await axios.post('/projects', { name })).data
                    : { id: localId(), name };
                localProjects.value.push(project);
                persistLocal();
                success('Project added');
                return project;
            } catch (err) {
                error('Failed to add project');
                return null;
            }
        };

        const addProject = async () => {
            isAddingProject.value = true;
            if (await createProject(newProjectName.value)) newProjectName.value = '';
            isAddingProject.value = false;
        };

        const updateProject = async (project) => {
            const previous = renaming[project.id];
            delete renaming[project.id];
            project.name = project.name.trim();
            if (!project.name && previous !== undefined) project.name = previous;
            if (project.name === previous) return;
            if (props.isAuthenticated) {
                try { await axios.patch(`/projects/${project.id}`, { name: project.name }); }
                catch { error('Failed to update project'); }
            } else {
                persistLocal();
            }
        };

        const cancelRename = (project, event) => {
            if (renaming[project.id] !== undefined) project.name = renaming[project.id];
            event.target.blur();
        };

        const openDeleteProject = (projectId, projectName) => {
            showConfirm('Delete project', `Delete "${projectName}"? This cannot be undone.`, () => deleteProject(projectId));
        };

        const deleteProject = async (projectId) => {
            try {
                if (props.isAuthenticated) await axios.delete(`/projects/${projectId}`);
                localProjects.value = localProjects.value.filter(p => p.id !== projectId);
                persistLocal();
                success('Project deleted');
            } catch (err) {
                error('Failed to delete project');
            }
        };

        return {
            localProjects, newProjectName,
            isAddingProject, confirmDialog,
            handleConfirm, handleCancel,
            addProject, createProject, updateProject, cancelRename, openDeleteProject, renaming,
            isDiscordActivity, closePanelRef, editing, editable,
        };
    }
};
</script>
