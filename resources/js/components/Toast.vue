<template>
    <div
        class="toast-stack fixed z-[100] flex flex-col gap-2 pointer-events-none"
        :class="{ 'toast-stack-centered': isDiscordActivity }"
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
import { isDiscordActivity } from '../discord.js';

export default {
    setup() {
        const { toasts } = useToast();
        return { toasts, isDiscordActivity };
    }
};
</script>

<style scoped>
.toast-stack {
    top: calc(1rem + var(--sait));
    right: calc(1rem + var(--sair));
}

/* In the Activity the header buttons and the projects sheet's close button
   live in the top-right corner. */
.toast-stack-centered {
    right: auto;
    left: 50%;
    align-items: center;
    transform: translateX(-50%);
}

.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateX(2rem);
}

.toast-stack-centered .toast-enter-from,
.toast-stack-centered .toast-leave-to {
    transform: translateY(-1rem);
}
</style>
