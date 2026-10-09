import { ref, computed, onMounted, onUnmounted } from 'vue';

// Whether the timer is counting down, set by Timer.vue.
export const timerRunning = ref(false);

const IDLE_MS = 3000;
const ACTIVITY_EVENTS = ['pointermove', 'pointerdown', 'keydown', 'wheel', 'touchstart'];
const isTyping = () => ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

/**
 * True while the timer runs and the user has not moved, tapped or typed for
 * a few seconds: the screen can then hide everything but the time. Any
 * input brings the controls back, like a video player.
 */
export function useQuietWhileFocusing({ enabled, blocked }) {
    const idle = ref(false);
    let timer = null;

    const wake = () => {
        idle.value = false;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (isTyping()) wake();
            else idle.value = true;
        }, IDLE_MS);
    };

    onMounted(() => {
        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, wake, { passive: true }));
        wake();
    });
    onUnmounted(() => {
        ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, wake));
        clearTimeout(timer);
    });

    return computed(() => enabled.value && !blocked.value && timerRunning.value && idle.value);
}
