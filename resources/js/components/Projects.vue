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
        :createProject="createProject"
        @manageProjects="$emit('openPanel')"
    />

    <!-- Managing projects is occasional: it opens as a dialog like Settings and
         the Report, from the header or from the picker, instead of sitting
         under the timer. -->
    <teleport to="body">
    <div
        v-if="panelOpen"
        class="safe-area fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="projects-title"
        @click.self="$emit('closePanel')"
    >
    <div
        class="modal-content modal-surface flex flex-col w-full max-w-md mx-4 p-4 sm:p-6 short:p-3 bg-white/10 backdrop-blur-lg text-white rounded-lg shadow-xl"
        @keydown.esc="$emit('closePanel')"
    >
        <div class="shrink-0 flex justify-between items-start pb-4 short:pb-2 border-b border-white/20">
            <div>
                <h2 id="projects-title" class="text-2xl short:text-xl font-bold font-oswald text-white">Projects</h2>
                <p v-if="!isAuthenticated" class="mt-0.5 text-xs text-white/50 font-inter">
                    Saved on this device<template v-if="!isDiscordActivity"> ·
                    <a href="/login" class="underline hover:text-white/80 transition">Sign in to sync</a></template>
                </p>
            </div>
            <button
                ref="closePanelRef"
                @click="$emit('closePanel')"
                aria-label="Close projects"
                class="w-11 h-11 -mr-3 -mt-2.5 flex items-center justify-center text-white hover:text-gray-300 transition"
            >
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Padded by as much as the rows reach out (-mx-2), so their hover
             background fits without a sideways scroll. -->
        <div class="modal-body flex-1 min-h-0 -mx-2 px-2 pt-3 overflow-y-auto overflow-x-hidden">

        <p v-if="localProjects.length === 0" class="text-white/50 text-sm font-inter py-3">
            Group your focus sessions by project, then pick one under the timer.
        </p>

        <!-- A calm list: names are text, renamed by clicking them; the rarer
             actions wait in a menu. -->
        <ul class="-mx-2" aria-label="Projects">
            <li
                v-for="project in localProjects"
                :key="project.id"
                class="project-row group relative flex items-center gap-1 min-h-11 px-2 rounded-lg hover:bg-white/5 transition-colors"
            >
                <input
                    v-if="renamingId === project.id"
                    :id="`rename-${project.id}`"
                    v-model="project.name"
                    @focus="renaming[project.id] = project.name"
                    @keydown.enter.prevent="$event.target.blur()"
                    @keydown.esc.stop="cancelRename(project, $event)"
                    @blur="finishRename(project)"
                    maxlength="255"
                    :aria-label="`Rename project ${project.name}`"
                    class="flex-1 min-w-0 my-1 bg-white/10 border-0 rounded-md px-2 py-1.5 text-sm font-inter font-semibold text-white focus:outline-none focus:ring-1 focus:ring-white/60"
                >
                <button
                    v-else
                    @click="startRename(project)"
                    :title="`Rename ${project.name}`"
                    class="flex-1 min-w-0 py-2.5 text-left truncate text-sm font-inter font-semibold text-white cursor-text"
                >
                    {{ project.name }}
                </button>

                <div class="relative shrink-0" :data-menu="project.id">
                    <button
                        @click="toggleMenu(project.id, $event)"
                        :aria-expanded="menuId === project.id"
                        aria-haspopup="menu"
                        :aria-label="`Actions for ${project.name}`"
                        class="row-actions w-9 h-9 flex items-center justify-center rounded-md text-white/50 hover:text-white hover:bg-white/10 transition"
                    >
                        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
                    </button>
                    <div
                        v-if="menuId === project.id"
                        role="menu"
                        class="absolute right-0 z-10 w-36 py-1 bg-neutral-800 border border-white/10 rounded-lg shadow-xl"
                        :class="menuUp ? 'bottom-full mb-1' : 'top-full mt-1'"
                        @keydown.esc.stop="menuId = null"
                    >
                        <button
                            role="menuitem"
                            @click="startRename(project)"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm font-inter text-white/80 hover:bg-white/10 hover:text-white"
                        >
                            <i class="fas fa-pencil-alt w-3 text-xs" aria-hidden="true"></i>Rename
                        </button>
                        <button
                            role="menuitem"
                            @click="menuId = null; openDeleteProject(project.id, project.name)"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-sm font-inter text-red-400 hover:bg-red-500/10"
                        >
                            <i class="fas fa-trash w-3 text-xs" aria-hidden="true"></i>Delete
                        </button>
                    </div>
                </div>
            </li>
        </ul>

        <!-- One quiet row to add a project, a field only once asked for. -->
        <form v-if="adding" @submit.prevent="addProject" class="-mx-2 px-2 py-1">
            <input
                ref="newProjectRef"
                v-model="newProjectName"
                @keydown.esc.stop="stopAdding"
                @blur="!newProjectName.trim() && stopAdding()"
                type="text"
                maxlength="255"
                placeholder="Project name, then Enter"
                aria-label="New project name"
                :disabled="isAddingProject"
                class="w-full bg-white/10 border-0 rounded-md px-2 py-2 text-sm font-inter text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white/60"
            >
        </form>
        <button
            v-else
            @click="startAdding"
            class="-mx-2 w-[calc(100%+1rem)] flex items-center gap-2.5 min-h-11 px-2 rounded-lg text-sm font-inter text-white/60 hover:text-white hover:bg-white/5 transition-colors"
        >
            <i class="fas fa-plus w-3 text-xs" aria-hidden="true"></i>New project
        </button>
        </div>
    </div>
    </div>
    </teleport>
</template>

<script>
import { ref, reactive, watch, nextTick, onMounted, onUnmounted } from 'vue';
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

        const closePanelRef = ref(null);
        const renamingId = ref(null);
        const menuId = ref(null);
        const adding = ref(false);
        const newProjectRef = ref(null);

        watch(() => props.panelOpen, async (open) => {
            if (!open) {
                renamingId.value = null;
                menuId.value = null;
                adding.value = false;
                return;
            }
            await nextTick();
            closePanelRef.value?.focus();
        });

        // The list scrolls inside the dialog and would cut a menu off at its
        // bottom edge, so a menu without room below opens upward.
        const menuUp = ref(false);
        const MENU_HEIGHT = 88;

        const toggleMenu = (id, event) => {
            const button = event.currentTarget;
            const list = button.closest('.modal-body');
            if (list) {
                menuUp.value = list.getBoundingClientRect().bottom - button.getBoundingClientRect().bottom < MENU_HEIGHT;
            }
            menuId.value = menuId.value === id ? null : id;
        };

        // Close a row's menu on a click anywhere else.
        const closeMenuOutside = (event) => {
            if (menuId.value !== null && !event.target.closest(`[data-menu="${menuId.value}"]`)) {
                menuId.value = null;
            }
        };
        onMounted(() => document.addEventListener('mousedown', closeMenuOutside));
        onUnmounted(() => document.removeEventListener('mousedown', closeMenuOutside));

        const startRename = async (project) => {
            menuId.value = null;
            renamingId.value = project.id;
            await nextTick();
            const input = document.getElementById(`rename-${project.id}`);
            input?.focus();
            input?.select();
        };

        const finishRename = (project) => {
            renamingId.value = null;
            updateProject(project);
        };

        const startAdding = async () => {
            adding.value = true;
            await nextTick();
            newProjectRef.value?.focus();
        };

        const stopAdding = () => {
            adding.value = false;
            newProjectName.value = '';
        };

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

        // The field stays open after Enter, to add several in a row.
        const addProject = async () => {
            isAddingProject.value = true;
            if (await createProject(newProjectName.value)) newProjectName.value = '';
            isAddingProject.value = false;
            await nextTick();
            newProjectRef.value?.focus();
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
            isDiscordActivity, closePanelRef, renamingId, menuId, menuUp, toggleMenu,
            startRename, finishRename, adding, newProjectRef, startAdding, stopAdding,
        };
    }
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    max-height: min(90vh, calc(100% - 2rem));
    overflow: hidden;
}

/* With a mouse, a row's menu button shows on hover or focus only; touch
   screens always show it. */
@media (hover: hover) {
    .row-actions {
        opacity: 0;
    }

    .project-row:hover .row-actions,
    .row-actions:focus-visible,
    .row-actions[aria-expanded="true"] {
        opacity: 1;
    }
}
</style>
