<template>
    <div class="flex flex-wrap justify-center gap-2 max-w-xl px-4" role="radiogroup" aria-label="Project for this session">
        <!-- No chip picked is general focus: tapping the picked chip again unpicks it. -->
        <button
            v-for="project in projects"
            :key="project.id"
            @click="toggle(project)"
            type="button"
            role="radio"
            :aria-checked="isSelected(project)"
            class="chip"
            :class="isSelected(project) ? 'bg-white text-black border-white' : 'text-white border-white/30 hover:border-white/70'"
        >
            <span class="truncate">{{ project.name }}</span>
        </button>

        <form v-if="adding" @submit.prevent="submit" class="flex">
            <input
                ref="inputRef"
                v-model="newName"
                @keydown.esc.stop="cancel"
                @blur="!newName.trim() && cancel()"
                type="text"
                maxlength="255"
                placeholder="New project"
                aria-label="New project name"
                :disabled="saving"
                class="chip w-40 bg-white/10 text-white border-white/70 placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white"
            >
        </form>
        <button
            v-else
            @click="startAdding"
            type="button"
            class="chip text-white/70 border-dashed border-white/30 hover:text-white hover:border-white/70"
            :aria-label="projects.length ? 'New project' : null"
        >
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span v-if="!projects.length">New project</span>
        </button>
    </div>
</template>

<script>
import { ref, nextTick } from 'vue';

const PREFIX = 'project:rbNiqBehszLPVzMmR_';

export default {
    props: {
        modelValue: { type: String, default: '' },
        projects: { type: Array, default: () => [] },
        // Saves a project by name and resolves to it, or to null on failure.
        createProject: { type: Function, required: true },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const adding = ref(false);
        const saving = ref(false);
        const newName = ref('');
        const inputRef = ref(null);

        const isSelected = (project) => props.modelValue === PREFIX + project.id;

        const toggle = (project) => {
            emit('update:modelValue', isSelected(project) ? '' : PREFIX + project.id);
        };

        const startAdding = async () => {
            adding.value = true;
            await nextTick();
            inputRef.value?.focus();
        };

        const cancel = () => {
            adding.value = false;
            newName.value = '';
        };

        // A new project is what the user is about to work on: pick it.
        const submit = async () => {
            const name = newName.value.trim();
            if (!name || saving.value) return;
            saving.value = true;
            const project = await props.createProject(name);
            saving.value = false;
            if (!project) return;
            emit('update:modelValue', PREFIX + project.id);
            cancel();
        };

        return { adding, saving, newName, inputRef, isSelected, toggle, startAdding, cancel, submit };
    },
};
</script>

<style scoped>
.chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    max-width: 14rem;
    min-height: 2.25rem;
    padding: 0.375rem 0.875rem;
    border-width: 1px;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    transition: all 0.2s;
}

.chip:focus-visible {
    outline: 2px solid white;
    outline-offset: 2px;
}

@media (max-height: 500px) {
    .chip {
        min-height: 1.75rem;
        padding: 0.125rem 0.75rem;
    }
}
</style>
