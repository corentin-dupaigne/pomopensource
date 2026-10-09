<template>
    <section class="day-log bg-white/10 backdrop-blur-lg rounded-lg p-6 short:p-3 shadow-lg" aria-labelledby="day-log-title">
        <div class="flex items-baseline justify-between gap-4 mb-3">
            <h3 id="day-log-title" class="text-lg font-semibold text-white">{{ dayLabel }}</h3>
            <span v-if="totalMinutes > 0" class="text-sm font-semibold text-white/70">{{ formatMinutes(totalMinutes) }}</span>
        </div>

        <div v-if="isLoading" class="flex justify-center py-4">
            <i class="fas fa-spinner fa-spin text-xl text-white/40" aria-label="Loading sessions"></i>
        </div>
        <p v-else-if="groups.length === 0" class="text-white/50 text-sm text-center py-2">
            No focus sessions this day.
        </p>
        <ul v-else class="divide-y divide-white/10" aria-label="Sessions">
            <li v-for="group in groups" :key="group.ids.join('-')" class="py-2 text-sm">
                <div class="flex items-baseline gap-3 text-white">
                    <span class="w-12 shrink-0 tabular-nums text-white/60">{{ formatClock(group.started_at) }}</span>
                    <span class="flex-1 min-w-0 truncate font-semibold" :class="{ 'text-white/60 font-normal': !group.project }">
                        {{ group.project?.name ?? 'General focus' }}
                    </span>
                    <span class="shrink-0 tabular-nums text-white/80">
                        {{ formatMinutes(group.minutes) }}<span v-if="group.ids.length > 1" class="text-white/50"> · {{ group.ids.length }} pomodoros</span>
                    </span>
                </div>
                <div class="pl-15 mt-0.5">
                    <input
                        v-if="editingKey === keyOf(group)"
                        ref="editInputRef"
                        v-model="draft"
                        @keydown.enter.prevent="saveNote(group)"
                        @keydown.esc.stop="editingKey = null"
                        @blur="saveNote(group)"
                        type="text"
                        maxlength="255"
                        :aria-label="`Note for the session at ${formatClock(group.started_at)}`"
                        class="w-full bg-transparent border-0 border-b border-white/40 px-0 py-0.5 text-sm text-white focus:outline-none focus:ring-0 focus:border-white"
                    >
                    <button
                        v-else
                        @click="startEditing(group)"
                        type="button"
                        class="max-w-full text-left truncate transition"
                        :class="group.note ? 'text-white/80 hover:text-white' : 'text-white/35 hover:text-white/70 italic'"
                        :aria-label="group.note ? `Edit note: ${group.note}` : 'Add a note'"
                    >
                        {{ group.note || 'add a note' }}
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>

<script>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import {
    parseLocalDate, getLocalSessions, getLocalProjects, computeDayLog, updateLocalSessionNotes,
} from '../composables/localStats.js';

// Sessions on the same thing, less than this apart, read as one stretch.
const MERGE_GAP_MINUTES = 30;

export default {
    props: {
        // Local 'YYYY-MM-DD'.
        date: { type: String, required: true },
        isAuthenticated: { type: [Boolean, Number], default: false },
    },
    setup(props) {
        const { error } = useToast();
        const sessions = ref([]);
        const isLoading = ref(false);
        const editingKey = ref(null);
        const draft = ref('');
        const editInputRef = ref(null);

        const load = async () => {
            editingKey.value = null;
            if (!props.isAuthenticated) {
                sessions.value = computeDayLog(getLocalSessions(), getLocalProjects(), props.date);
                return;
            }
            isLoading.value = true;
            try {
                const { data } = await axios.get(`/user-stats/day/${props.date}`);
                sessions.value = data.sessions;
            } catch (err) {
                console.error('Error fetching the day log:', err);
                sessions.value = [];
            } finally {
                isLoading.value = false;
            }
        };
        watch(() => props.date, load, { immediate: true });

        const endOf = (session) => new Date(session.started_at).getTime() + session.minutes_focused * 60000;

        // Back-to-back pomodoros on the same project and note make one line.
        const groups = computed(() => {
            const result = [];
            for (const session of sessions.value) {
                const last = result[result.length - 1];
                const sameThing = last
                    && (last.project?.id ?? null) === (session.project?.id ?? null)
                    && (last.note ?? '') === (session.note ?? '');
                const close = last && (!session.started_at || !last.lastSession.started_at
                    || new Date(session.started_at).getTime() - endOf(last.lastSession) < MERGE_GAP_MINUTES * 60000);
                if (sameThing && close) {
                    last.ids.push(session.id);
                    last.minutes += session.minutes_focused;
                    last.lastSession = session;
                } else {
                    result.push({
                        ids: [session.id],
                        started_at: session.started_at,
                        minutes: session.minutes_focused,
                        project: session.project,
                        note: session.note,
                        lastSession: session,
                    });
                }
            }
            return result;
        });

        const totalMinutes = computed(() => sessions.value.reduce((sum, s) => sum + s.minutes_focused, 0));

        const dayLabel = computed(() => parseLocalDate(props.date)
            .toLocaleDateString('default', { weekday: 'short', day: 'numeric', month: 'short' }));

        const keyOf = (group) => group.ids.join('-');

        const startEditing = async (group) => {
            draft.value = group.note ?? '';
            editingKey.value = keyOf(group);
            await nextTick();
            editInputRef.value?.[0]?.focus();
        };

        const saveNote = async (group) => {
            // Enter saves, then the input's blur would save again.
            if (editingKey.value !== keyOf(group)) return;
            editingKey.value = null;
            const note = draft.value.trim() || null;
            if (note === (group.note ?? null)) return;

            try {
                if (props.isAuthenticated) {
                    await axios.patch('/focused-sessions/notes', { ids: group.ids, note });
                } else {
                    updateLocalSessionNotes(group.ids, note);
                }
                for (const session of sessions.value) {
                    if (group.ids.includes(session.id)) session.note = note;
                }
            } catch (err) {
                error('Failed to save the note');
            }
        };

        const formatClock = (startedAt) => startedAt
            ? new Date(startedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
            : '';

        const formatMinutes = (minutes) => {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            if (h === 0) return `${m}m`;
            return m === 0 ? `${h}h` : `${h}h ${m}m`;
        };

        return {
            isLoading, groups, totalMinutes, dayLabel, editingKey, draft, editInputRef,
            keyOf, startEditing, saveNote, formatClock, formatMinutes,
        };
    },
};
</script>

<style scoped>
.day-log {
    background-color: rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    backdrop-filter: blur(10px);
}

/* Lines the note up under the project name, past the time column. */
.pl-15 {
    padding-left: 3.75rem;
}
</style>
