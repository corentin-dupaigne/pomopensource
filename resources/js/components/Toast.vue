<template>
    <div
        class="toast-stack hide-when-minimal fixed z-[100] flex flex-col gap-2 pointer-events-none"
        aria-live="polite"
        aria-atomic="false"
    >
        <transition-group name="toast">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg pointer-events-auto text-white text-sm font-inter min-w-[220px] max-w-xs backdrop-blur-sm"
                :class="toast.type === 'error' ? 'bg-red-600/90' : 'bg-green-600/90'"
                role="alert"
            >
                <i
                    :class="toast.type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'"
                    class="shrink-0"
                    aria-hidden="true"
                ></i>
                <span>{{ toast.message }}</span>
            </div>
        </transition-group>
    </div>
</template>

<script>
import { useToast } from '../composables/toast.js';

export default {
    setup() {
        const { toasts } = useToast();
        return { toasts };
    }
};
</script>

<style scoped>
/* Top centre: the top-right corner holds the header buttons and the close
   button of the projects sheet. */
.toast-stack {
    top: calc(1rem + var(--sait));
    left: 50%;
    transform: translateX(-50%);
    align-items: center;
}

.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-0.75rem);
}
</style>
