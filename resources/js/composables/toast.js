import { ref } from 'vue';

const toasts = ref([]);
let nextId = 0;

export function useToast() {
    const dismiss = (id) => {
        const toast = toasts.value.find(t => t.id === id);
        toasts.value = toasts.value.filter(t => t.id !== id);
        toast?.onDismiss?.();
    };

    const show = (message, type = 'success', duration = 3500) => {
        const id = ++nextId;
        toasts.value.push({ id, message, type });
        setTimeout(() => {
            toasts.value = toasts.value.filter(t => t.id !== id);
        }, duration);
    };

    return {
        toasts,
        dismiss,
        success: (msg) => show(msg, 'success'),
        error: (msg) => show(msg, 'error'),
        // News about the app: stays until read and dismissed.
        notice: (message, onDismiss) => {
            toasts.value.push({ id: ++nextId, message, type: 'notice', onDismiss });
        },
    };
}
