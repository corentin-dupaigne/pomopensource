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
                class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg pointer-events-auto text-white text-sm min-w-[220px] backdrop-blur-sm"
                :class="{
                    'bg-red-600/90 max-w-xs': toast.type === 'error',
                    'bg-green-600/90 max-w-xs': toast.type === 'success',
                    'bg-neutral-900/90 border border-white/20 max-w-sm': toast.type === 'notice',
                }"
                :role="toast.type === 'notice' ? 'status' : 'alert'"
            >
                <i
                    :class="{ error: 'fas fa-exclamation-circle', success: 'fas fa-check-circle', notice: 'fas fa-info-circle text-white/60' }[toast.type]"
                    class="shrink-0"
                    aria-hidden="true"
                ></i>
                <span>{{ toast.message }}</span>
                <button
                    v-if="toast.type === 'notice'"
                    @click="dismiss(toast.id)"
                    type="button"
                    class="shrink-0 -my-1 px-3 py-1.5 rounded-md bg-white/10 hover:bg-white/20 font-semibold transition"
                >
                    Got it
                </button>
            </div>
        </transition-group>
    </div>
</template>

<script>
import { useToast } from '../composables/toast.js';

export default {
    setup() {
        const { toasts, dismiss } = useToast();
        return { toasts, dismiss };
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
