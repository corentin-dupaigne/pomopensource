<template>
    <!-- Embedded: the left part of the session bar in Timer.vue, borderless and
         sized to its label; the list keeps a usable width. -->
    <div class="relative" :class="embedded ? 'shrink-0 max-w-[45%]' : 'w-72 max-w-[calc(100vw-2rem)]'" ref="containerRef">
        <!-- Trigger -->
        <button
            ref="triggerRef"
            @click="toggle"
            @keydown="handleTriggerKeydown"
            type="button"
            class="w-full flex items-center justify-between text-white transition duration-200 focus:outline-none"
            :class="embedded
                ? 'h-full pl-3 pr-2 py-2.5 short:py-2 rounded-l-lg hover:bg-white/10 focus-visible:ring-1 focus-visible:ring-white'
                : 'px-4 py-3 short:py-2 bg-white/10 backdrop-blur-sm border border-white/30 rounded-lg hover:bg-white/20 focus:ring-1 focus:ring-white'"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            aria-label="Select project for this session"
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
                class="absolute left-0 bg-neutral-900/90 backdrop-blur-lg border border-white/20 rounded-lg shadow-2xl overflow-hidden z-20 overflow-y-auto"
                :class="[openUpward ? 'bottom-full mb-2' : 'top-full mt-2', embedded ? 'w-64 max-w-[calc(100vw-2rem)]' : 'right-0']"
                :style="{ maxHeight: `${maxHeight}px` }"
                role="listbox"
                aria-label="Select project"
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

const MAX_LIST_HEIGHT = 256;
const MIN_LIST_HEIGHT = 160;
const EDGE_MARGIN = 16;

export default {
    props: {
        modelValue: { type: String, default: '' },
        projects: { type: Array, default: () => [] },
        embedded: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const isOpen = ref(false);
        const containerRef = ref(null);
        const triggerRef = ref(null);
        const listboxRef = ref(null);
        const openUpward = ref(false);
        const maxHeight = ref(MAX_LIST_HEIGHT);

        const selectedLabel = computed(() => {
            if (!props.modelValue) return 'General focus';
            for (const project of props.projects) {
                if (props.modelValue === 'project:rbNiqBehszLPVzMmR_' + project.id) {
                    return project.name;
                }
            }
            return 'General focus';
        });

        const selectedIcon = computed(() => {
            return props.modelValue ? 'fas fa-folder' : 'fas fa-infinity';
        });

        const getListItems = () =>
            Array.from(listboxRef.value?.querySelectorAll('button') ?? []);

        // The page does not scroll inside a Discord Activity, so a list that
        // runs past the bottom of the frame could never be reached: open it
        // upward when there is more room above, and never taller than the room.
        const placeList = () => {
            const rect = triggerRef.value.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom - EDGE_MARGIN;
            const above = rect.top - EDGE_MARGIN;
            openUpward.value = below < MIN_LIST_HEIGHT && above > below;
            maxHeight.value = Math.max(0, Math.min(MAX_LIST_HEIGHT, openUpward.value ? above : below));
        };

        const open = () => {
            placeList();
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
            isOpen, containerRef, triggerRef, listboxRef, openUpward, maxHeight,
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
