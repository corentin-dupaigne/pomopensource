<template>
    <div class="relative" ref="containerRef">
        <!-- Trigger -->
        <button
            ref="triggerRef"
            @click="toggle"
            @keydown="handleTriggerKeydown"
            type="button"
            class="w-full flex items-center justify-between mt-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm font-inter hover:bg-white/15 transition duration-150 focus:outline-none focus:ring-1 focus:ring-white/40"
            :aria-expanded="isOpen"
            aria-haspopup="listbox"
            :id="triggerId"
        >
            <span class="truncate">{{ modelValue || placeholder }}</span>
            <i
                class="fas fa-chevron-down text-white/50 text-xs transition-transform duration-200 shrink-0 ml-2"
                :class="{ 'rotate-180': isOpen }"
                aria-hidden="true"
            ></i>
        </button>

        <!-- Dropdown — teleported to body to escape overflow:hidden containers -->
        <teleport to="body">
            <transition name="dropdown">
                <div
                    v-if="isOpen"
                    ref="listboxRef"
                    :style="dropdownStyle"
                    class="fixed bg-white/15 backdrop-blur-xl border border-white/20 rounded-lg shadow-2xl overflow-hidden overflow-y-auto z-[200] scrollbar-dropdown"
                    style="max-height: 14rem;"
                    role="listbox"
                    :aria-labelledby="triggerId"
                    @keydown="handleListKeydown"
                >
                    <button
                        v-for="option in options"
                        :key="option"
                        @click="select(option)"
                        type="button"
                        class="w-full text-left py-2 text-sm font-inter transition border-l-2"
                        :class="isSelected(option)
                            ? 'border-white/70 pl-3 pr-3 text-white font-semibold'
                            : 'border-transparent pl-3 pr-3 text-white/80 hover:text-white hover:bg-white/10'"
                        role="option"
                        :aria-selected="isSelected(option)"
                    >
                        {{ option }}
                    </button>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

export default {
    props: {
        modelValue: { type: String, default: '' },
        options: { type: Array, default: () => [] },
        placeholder: { type: String, default: 'Select...' },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const isOpen = ref(false);
        const containerRef = ref(null);
        const triggerRef = ref(null);
        const listboxRef = ref(null);
        const triggerId = `custom-select-${Math.random().toString(36).slice(2)}`;
        const dropdownStyle = ref({});

        const updatePosition = () => {
            if (!triggerRef.value) return;
            const rect = triggerRef.value.getBoundingClientRect();
            dropdownStyle.value = {
                top: `${rect.bottom + 4}px`,
                left: `${rect.left}px`,
                width: `${rect.width}px`,
            };
        };

        const getListItems = () =>
            Array.from(listboxRef.value?.querySelectorAll('button') ?? []);

        const focusSelected = () => {
            nextTick(() => {
                const items = getListItems();
                const idx = items.findIndex(b => b.getAttribute('aria-selected') === 'true');
                items[idx >= 0 ? idx : 0]?.focus();
            });
        };

        const open = () => {
            updatePosition();
            isOpen.value = true;
            focusSelected();
        };

        const close = () => {
            isOpen.value = false;
        };

        const toggle = () => {
            if (isOpen.value) close();
            else open();
        };

        const isSelected = (option) =>
            props.modelValue?.toLowerCase() === option?.toLowerCase();

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
            if (
                containerRef.value && !containerRef.value.contains(e.target) &&
                !e.target.closest('[role="listbox"]')
            ) {
                close();
            }
        };

        const syncDefault = () => {
            if (props.options.length === 0) return;
            const hasMatch = props.options.some(o => isSelected(o));
            if (!hasMatch) emit('update:modelValue', props.options[0]);
        };

        onMounted(() => {
            document.addEventListener('mousedown', handleOutsideClick);
            syncDefault();
        });
        watch(() => props.options, syncDefault);
        onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));

        return {
            isOpen, containerRef, triggerRef, listboxRef,
            triggerId, dropdownStyle,
            toggle, close, select, isSelected,
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

<style>
.scrollbar-dropdown::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-dropdown::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-dropdown::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
}
.scrollbar-dropdown::-webkit-scrollbar-thumb:hover {
    background-color: rgba(255, 255, 255, 0.35);
}
</style>
