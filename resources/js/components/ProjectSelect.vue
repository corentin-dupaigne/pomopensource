<template>
    <div class="relative w-72" ref="containerRef">
        <!-- Trigger -->
        <button
            ref="triggerRef"
            @click="toggle"
            @keydown="handleTriggerKeydown"
            type="button"
            class="w-full flex items-center justify-between px-4 py-3 bg-white/10 backdrop-blur-sm border border-white/30 rounded-lg text-white hover:bg-white/20 transition duration-200 focus:outline-none focus:ring-1 focus:ring-white"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            aria-label="Select project or task for this session"
        >
            <div class="flex items-center gap-2 min-w-0">
                <i :class="selectedIcon" class="text-white/50 text-xs shrink-0" aria-hidden="true"></i>
                <span class="text-sm font-inter text-white truncate">{{ selectedLabel }}</span>
            </div>
            <i
                class="fas fa-chevron-down text-white/50 text-xs transition-transform duration-200 shrink-0 ml-2"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            ></i>
        </button>

        <!-- Dropdown panel -->
        <transition name="dropdown">
            <div
                v-if="isOpen"
                ref="listboxRef"
                class="absolute top-full left-0 right-0 mt-2 bg-black/40 backdrop-blur-lg border border-white/20 rounded-lg shadow-2xl overflow-hidden z-20 max-h-64 overflow-y-auto"
                role="listbox"
                aria-label="Select project or task"
                @keydown="handleListKeydown"
            >
                <!-- General focus -->
                <button
                    @click="select('')"
                    type="button"
                    class="w-full text-left px-4 py-2.5 text-sm font-inter flex items-center gap-3 transition"
                    :class="modelValue === '' ? 'bg-white/20 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white'"
                    role="option"
                    :aria-selected="modelValue === ''"
                >
                    <i class="fas fa-infinity text-white/40 text-xs w-3" aria-hidden="true"></i>
                    <span>General focus</span>
                </button>

                <!-- Projects -->
                <template v-if="projects.length > 0">
                    <div class="border-t border-white/10 mx-3 my-1" aria-hidden="true"></div>

                    <template v-for="project in projects" :key="project.id">
                        <button
                            @click="select('project:rbNiqBehszLPVzMmR_' + project.id)"
                            type="button"
                            class="w-full text-left px-4 py-2.5 text-sm font-inter font-semibold flex items-center gap-3 transition"
                            :class="modelValue === 'project:rbNiqBehszLPVzMmR_' + project.id
                                ? 'bg-white/20 text-white'
                                : 'text-white hover:bg-white/10'"
                            role="option"
                            :aria-selected="modelValue === 'project:rbNiqBehszLPVzMmR_' + project.id"
                        >
                            <i class="fas fa-folder text-white/50 text-xs w-3" aria-hidden="true"></i>
                            <span>{{ project.name }}</span>
                        </button>

                        <button
                            v-for="task in project.tasks"
                            :key="task.id"
                            @click="select('task:rbNiqBehszLPVzMmR_' + task.id)"
                            type="button"
                            class="w-full text-left pl-11 pr-4 py-2 text-sm font-inter flex items-center gap-3 transition"
                            :class="modelValue === 'task:rbNiqBehszLPVzMmR_' + task.id
                                ? 'bg-white/20 text-white'
                                : 'text-white/60 hover:bg-white/10 hover:text-white'"
                            role="option"
                            :aria-selected="modelValue === 'task:rbNiqBehszLPVzMmR_' + task.id"
                        >
                            <i class="fas fa-circle text-white/25 text-[8px] w-3" aria-hidden="true"></i>
                            <span>{{ task.name }}</span>
                        </button>
                    </template>
                </template>

                <div v-else class="px-4 py-3 text-sm text-white/40 font-inter italic">
                    No projects yet
                </div>
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';

export default {
    props: {
        modelValue: { type: String, default: '' },
        projects: { type: Array, default: () => [] },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const isOpen = ref(false);
        const containerRef = ref(null);
        const triggerRef = ref(null);
        const listboxRef = ref(null);

        const selectedLabel = computed(() => {
            if (!props.modelValue) return 'General focus';
            for (const project of props.projects) {
                if (props.modelValue === 'project:rbNiqBehszLPVzMmR_' + project.id) {
                    return project.name;
                }
                for (const task of project.tasks ?? []) {
                    if (props.modelValue === 'task:rbNiqBehszLPVzMmR_' + task.id) {
                        return `${project.name} › ${task.name}`;
                    }
                }
            }
            return 'General focus';
        });

        const selectedIcon = computed(() => {
            if (!props.modelValue) return 'fas fa-infinity';
            if (props.modelValue.startsWith('project:')) return 'fas fa-folder';
            return 'fas fa-circle text-[8px]';
        });

        const getListItems = () =>
            Array.from(listboxRef.value?.querySelectorAll('button') ?? []);

        const open = () => {
            isOpen.value = true;
            nextTick(() => {
                const items = getListItems();
                const idx = items.findIndex(b => b.getAttribute('aria-selected') === 'true');
                items[idx >= 0 ? idx : 0]?.focus();
            });
        };

        const close = () => { isOpen.value = false; };

        const toggle = () => {
            if (isOpen.value) close();
            else open();
        };

        const select = (value) => {
            emit('update:modelValue', value);
            close();
            triggerRef.value?.focus();
        };

        const handleTriggerKeydown = (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen.value) open();
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggle();
            }
        };

        const handleListKeydown = (e) => {
            const items = getListItems();
            const currentIdx = items.indexOf(document.activeElement);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                items[Math.min(currentIdx + 1, items.length - 1)]?.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (currentIdx <= 0) {
                    close();
                    triggerRef.value?.focus();
                } else {
                    items[currentIdx - 1]?.focus();
                }
            } else if (e.key === 'Escape') {
                close();
                triggerRef.value?.focus();
            } else if (e.key === 'Tab') {
                close();
            }
        };

        const handleOutsideClick = (e) => {
            if (containerRef.value && !containerRef.value.contains(e.target)) {
                close();
            }
        };

        onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
        onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));

        return {
            isOpen, containerRef, triggerRef, listboxRef,
            selectedLabel, selectedIcon,
            toggle, close, select,
            handleTriggerKeydown, handleListKeydown,
        };
    }
};
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
