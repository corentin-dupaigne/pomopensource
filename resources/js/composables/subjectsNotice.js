import { ref } from 'vue';

// Whether to tell this account, or this device for a guest, that projects
// and notes became one field. Shown once, next to the field itself.
export const subjectsNotice = ref(false);
let onDismiss = null;

export function showSubjectsNotice(dismiss) {
    onDismiss = dismiss;
    subjectsNotice.value = true;
}

export function dismissSubjectsNotice() {
    if (!subjectsNotice.value) return;
    subjectsNotice.value = false;
    onDismiss?.();
}
