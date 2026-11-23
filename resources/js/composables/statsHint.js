import { ref } from 'vue';

// The minutes just saved, while the app points to the stats: once per
// account, after a pomodoro, if it never opened them (the server decides).
// Most people arrive in a friend's call and never learn their time is kept.
export const statsHint = ref(null);

export function dismissStatsHint() {
    statsHint.value = null;
}
