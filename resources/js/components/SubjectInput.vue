<template>
    <!-- "What are you working on?": one field, which suggests what this
         account typed before. The same text is the same subject. -->
    <div class="relative min-w-0" :class="inline ? 'inline-flex' : 'flex-1'" ref="containerRef">
        <input
            ref="inputRef"
            :value="modelValue"
            @input="onInput"
            @focus="open"
            @blur="onBlur"
            @keydown="onKeydown"
            type="text"
            maxlength="255"
            :size="inline ? Math.min(Math.max(modelValue.length, 12), 32) : undefined"
            placeholder="What are you working on?"
            aria-label="What are you working on?"
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="isOpen && suggestions.length > 0"
            :aria-controls="listId"
            :aria-activedescendant="activeIndex >= 0 ? `${listId}-${activeIndex}` : undefined"
            autocomplete="off"
            :class="inline
                ? 'min-w-0 [field-sizing:content] bg-transparent border-0 border-b border-transparent p-0 text-sm text-white placeholder-white/50 focus:outline-none focus:ring-0 focus:border-white/50 hover:border-white/30 transition-colors'
                : 'w-full bg-transparent border-0 px-3 py-2.5 short:py-2 text-sm text-white placeholder-white/60 focus:outline-none focus:ring-0'"
        >

        <transition name="dropdown">
            <div
                v-if="isOpen && (suggestions.length > 0 || canManage)"
                class="absolute left-0 w-64 max-w-[calc(100vw-2rem)] bg-neutral-900/90 backdrop-blur-lg border border-white/20 rounded-lg shadow-2xl overflow-hidden overflow-y-auto z-20"
                :class="openUpward ? 'bottom-full mb-2' : 'top-full mt-2'"
                :style="{ maxHeight: `${maxHeight}px` }"
            >
                <ul :id="listId" role="listbox" aria-label="Subjects you worked on">
                    <li
                        v-for="(name, index) in suggestions"
                        :key="name"
                        :id="`${listId}-${index}`"
                        role="option"
                        :aria-selected="index === activeIndex"
                        @mousedown.prevent="pick(name)"
                        class="px-4 py-2.5 text-sm truncate cursor-pointer transition"
                        :class="index === activeIndex ? 'bg-white/20 text-white' : 'text-white/80 hover:bg-white/10 hover:text-white'"
                    >
                        {{ name }}
                    </li>
                </ul>
                <template v-if="canManage">
                    <div v-if="suggestions.length > 0" class="border-t border-white/10 mx-3 my-1" aria-hidden="true"></div>
                    <button
                        type="button"
                        @mousedown.prevent="manage"
                        class="w-full text-left px-4 py-2 text-xs flex items-center gap-2.5 text-white/50 hover:bg-white/10 hover:text-white transition"
                    >
                        <i class="fas fa-sliders-h w-3" aria-hidden="true"></i>
                        <span>Rename or delete subjects…</span>
                    </button>
                </template>
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, computed } from 'vue';
import { subjectKey } from '../composables/subjects.js';

const MAX_SUGGESTIONS = 6;
const MAX_LIST_HEIGHT = 256;
const MIN_LIST_HEIGHT = 140;
const EDGE_MARGIN = 16;

let instances = 0;

export default {
    props: {
        modelValue: { type: String, default: '' },
        // The account's subjects, most recently used first.
        subjects: { type: Array, default: () => [] },
        // Quiet text under a running timer, rather than part of the bar.
        inline: { type: Boolean, default: false },
    },
    emits: ['update:modelValue', 'manage'],
    setup(props, { emit }) {
        const listId = `subject-list-${++instances}`;
        const containerRef = ref(null);
        const inputRef = ref(null);
        const isOpen = ref(false);
        const activeIndex = ref(-1);
        const openUpward = ref(false);
        const maxHeight = ref(MAX_LIST_HEIGHT);

        // Those containing what is typed, the ones starting with it first;
        // the subject already typed in full is not suggested again.
        const suggestions = computed(() => {
            const typed = subjectKey(props.modelValue);
            const names = props.subjects.map((s) => s.name);
            if (!typed) return names.slice(0, MAX_SUGGESTIONS);
            const matching = names.filter((name) => {
                const key = subjectKey(name);
                return key !== typed && key.includes(typed);
            });
            const starting = matching.filter((name) => subjectKey(name).startsWith(typed));
            return [...starting, ...matching.filter((name) => !starting.includes(name))].slice(0, MAX_SUGGESTIONS);
        });

        const canManage = computed(() => props.subjects.length > 0 && !props.modelValue.trim());

        // The page does not scroll inside a Discord Activity: open the list
        // upward when there is more room above, never taller than the room.
        const placeList = () => {
            const rect = inputRef.value.getBoundingClientRect();
            const below = window.innerHeight - rect.bottom - EDGE_MARGIN;
            const above = rect.top - EDGE_MARGIN;
            openUpward.value = below < MIN_LIST_HEIGHT && above > below;
            maxHeight.value = Math.max(0, Math.min(MAX_LIST_HEIGHT, openUpward.value ? above : below));
        };

        const open = () => {
            placeList();
            isOpen.value = true;
            activeIndex.value = -1;
        };

        const close = () => {
            isOpen.value = false;
            activeIndex.value = -1;
        };

        const onInput = (event) => {
            emit('update:modelValue', event.target.value);
            if (!isOpen.value) open();
            activeIndex.value = -1;
        };

        const pick = (name) => {
            emit('update:modelValue', name);
            close();
            inputRef.value?.blur();
        };

        const onBlur = () => {
            close();
            const cleaned = props.modelValue.replace(/\s+/g, ' ').trim();
            if (cleaned !== props.modelValue) emit('update:modelValue', cleaned);
        };

        const manage = () => {
            close();
            inputRef.value?.blur();
            emit('manage');
        };

        const onKeydown = (event) => {
            const count = suggestions.value.length;
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                if (!isOpen.value) open();
                if (count) activeIndex.value = (activeIndex.value + 1) % count;
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                if (count) activeIndex.value = activeIndex.value <= 0 ? count - 1 : activeIndex.value - 1;
            } else if (event.key === 'Enter') {
                event.preventDefault();
                if (isOpen.value && activeIndex.value >= 0) pick(suggestions.value[activeIndex.value]);
                else inputRef.value?.blur();
            } else if (event.key === 'Escape' && isOpen.value) {
                // Only closes the list: a second Escape is the page's.
                event.stopPropagation();
                close();
            }
        };

        return {
            listId, containerRef, inputRef, isOpen, activeIndex, openUpward, maxHeight,
            suggestions, canManage, open, onInput, onBlur, onKeydown, pick, manage,
        };
    },
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
