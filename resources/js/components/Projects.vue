<template>
    <ConfirmModal
        :visible="confirmDialog.visible"
        :title="confirmDialog.title"
        :message="confirmDialog.message"
        :confirmLabel="confirmDialog.confirmLabel"
        @confirm="handleConfirm"
        @cancel="handleCancel"
    />

    <Timer :projects="localProjects" :createProject="createProject" :settings="settings" :isAuthenticated="isAuthenticated" :zenMode="zenMode"/>

    <!-- On the website the list sits under the timer; in a Discord Activity it
         opens as a panel from the header. -->
    <teleport to="body" :disabled="!asPanel">
    <div
        v-if="!asPanel || panelOpen"
        :class="asPanel ? 'safe-area fixed inset-0 z-50 bg-black/70' : 'contents'"
        :role="asPanel ? 'dialog' : null"
        :aria-modal="asPanel ? 'true' : null"
        :aria-label="asPanel ? 'Projects' : null"
        @keydown.esc="$emit('closePanel')"
    >
    <!-- In the Activity: a sheet from the bottom on a phone, from the right otherwise. -->
    <div :class="asPanel ? 'w-full h-full flex items-end justify-center sm:justify-end sm:items-stretch' : 'contents'" @click.self="$emit('closePanel')">
    <div
        class="zen-fade w-full shadow-lg"
        :class="[{ 'zen-hidden': zenMode && !asPanel }, asPanel
            ? 'max-h-[85%] sm:max-h-full sm:max-w-md px-4 py-5 overflow-y-auto bg-neutral-900/80 backdrop-blur-lg text-white rounded-t-2xl sm:rounded-none'
            : ['max-w-3xl px-6 bg-white/10 rounded-lg', localProjects.length === 0 ? 'py-5' : 'py-8']]"
    >
        <div class="flex items-center justify-between" :class="asPanel ? 'mb-4' : 'mb-6'">
            <h2 class="font-bold font-oswald text-white" :class="asPanel ? 'text-2xl' : 'text-3xl'">Projects</h2>
            <div class="flex items-center gap-4">
                <!-- Renaming and deleting stay out of the way until asked for. -->
                <button
                    v-if="asPanel && localProjects.length > 0"
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
                    v-if="asPanel"
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
        <form v-if="asPanel" @submit.prevent="addProject" class="flex gap-2 mb-4">
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

        <p v-if="asPanel && localProjects.length === 0" class="text-white/50 text-sm font-inter text-center py-4">
            Group your focus sessions by project: pick one under the timer, or add one there.
        </p>

        <form v-else-if="!asPanel" @submit.prevent="addProject" class="mb-6">
            <div class="mb-4">
                <input
                    v-model="newProjectName"
                    type="text"
                    placeholder="New project name"
                    required
                    aria-label="New project name"
                    class="w-full py-3 px-4 bg-white/10 border border-white/30 rounded-lg text-center text-sm font-inter font-semibold text-white placeholder-white/70 focus:outline-none focus:border-white focus:ring-1 focus:ring-white transition duration-200"
                >
            </div>
            <button
                type="submit"
                :disabled="isAddingProject"
                aria-label="Add new project"
                class="w-full py-3 border-2 border-dashed border-white rounded-lg text-center text-sm font-inter font-semibold text-white hover:bg-white/20 transition duration-200 disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
            >
                <i v-if="isAddingProject" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                <span>{{ isAddingProject ? 'Adding...' : '+ Add Project' }}</span>
            </button>
        </form>

        <!-- Projects list -->
        <ul class="space-y-2" aria-label="Projects">
            <li v-for="project in localProjects" :key="project.id" class="group flex items-center gap-2 bg-white/5 rounded-lg px-4 py-3">
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
        asPanel: { type: Boolean, default: false },
        panelOpen: { type: Boolean, default: false },
    },
    emits: ['closePanel'],
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

        // In the Activity, names are plain text until the user asks to edit.
        const editing = ref(false);
        const editable = computed(() => !props.asPanel || editing.value);

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

        // Also used by the chips under the timer. Resolves to the project, or
        // to null once the failure has been reported.
        const createProject = async (name) => {
            try {
                let project;
                if (props.isAuthenticated) {
                    ({ data: project } = await axios.post('/projects', { name }));
                } else {
                    project = { id: localId(), name };
                }
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
            const name = newProjectName.value.trim();
            if (!name) return;
            isAddingProject.value = true;
            if (await createProject(name)) newProjectName.value = '';
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
