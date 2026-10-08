<template>
    <div class="flex flex-col items-center">
        <div class="zen-fade flex space-x-4 mb-8 short:mb-3" :class="{ 'zen-hidden': zenMode }" role="tablist" aria-label="Timer type">
            <button
                @click="setTimer('pomodoro')"
                id="default-timer"
                class="timer-button"
                :class="{ 'active-button': currentTimerType === 'pomodoro' }"
                :disabled="isRunning && currentTimerType === 'pomodoro'"
                role="tab"
                :aria-selected="currentTimerType === 'pomodoro'"
                aria-label="Pomodoro timer"
            >
                pomodoro
            </button>
            <button
                @click="setTimer('short_break')"
                class="timer-button"
                :class="{ 'active-button': currentTimerType === 'short_break' }"
                :disabled="isRunning && currentTimerType === 'pomodoro'"
                role="tab"
                :aria-selected="currentTimerType === 'short_break'"
                aria-label="Short break timer"
            >
                short break
            </button>
            <button
                @click="setTimer('long_break')"
                class="timer-button"
                :class="{ 'active-button': currentTimerType === 'long_break' }"
                :disabled="isRunning && currentTimerType === 'pomodoro'"
                role="tab"
                :aria-selected="currentTimerType === 'long_break'"
                aria-label="Long break timer"
            >
                long break
            </button>
        </div>

        <div
            id="timerDisplay"
            class="text-9xl font-oswald font-bold mb-8 short:text-7xl short:mb-3"
            :class="{ 'timer-done': justFinished, 'timer-fluid': isDiscordActivity }"
            :aria-label="`Timer: ${formattedTime}`"
            aria-live="off"
        >
            {{ formattedTime }}
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <div class="flex space-x-4 mb-8 short:mb-3">
            <button
                @click="toggleTimer"
                id="stop-start-button"
                class="control-button bg-white text-black border-2 border-transparent hover:bg-transparent hover:text-white hover:border-2 hover:border-white"
                :aria-label="isRunning ? 'Pause timer' : 'Start timer'"
            >
                {{ isRunning ? 'pause' : 'start' }}
            </button>
            <button
                @click="resetTimer"
                class="text-3xl"
                aria-label="Reset timer"
            >
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Project / task selector -->
        <ProjectSelect
            v-if="!isRunning && currentTimerType === 'pomodoro'"
            v-model="selectedId"
            :projects="projects"
            class="mb-4"
        />

        <!-- Selected context label while running -->
        <div
            v-if="isRunning && currentTimerType === 'pomodoro' && selectedId"
            class="mb-4 flex items-center justify-center gap-2 text-sm text-white/50 font-inter"
            aria-live="polite"
        >
            <i :class="selectedId.startsWith('project:') ? 'fas fa-folder' : 'fas fa-circle text-[10px]'" class="text-white/35" aria-hidden="true"></i>
            <span>{{ selectedLabel }}</span>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, watchEffect, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import { addLocalSession, toLocalDateString } from '../composables/localStats.js';
import { isEnabled } from '../composables/settings.js';
import { setPresence, timerPresence, isDiscordActivity } from '../discord.js';
import ProjectSelect from './ProjectSelect.vue';

const BASE_TITLE = 'Pomopensource';
const TIMER_LABELS = {
    pomodoro: 'Focus',
    short_break: 'Short break',
    long_break: 'Long break',
};

const LONG_BREAK_INTERVAL = 4;
const CYCLE_KEY = 'pomodoroCycle';

// Completed pomodoros, counted per local day.
const loadCompletedToday = () => {
    try {
        const { date, count } = JSON.parse(localStorage.getItem(CYCLE_KEY)) ?? {};
        return date === toLocalDateString(new Date()) ? count : 0;
    } catch {
        return 0;
    }
};

const DONE_MESSAGES = {
    pomodoro: { title: 'Pomodoro complete!', body: 'Time to take a break.' },
    break: { title: 'Break over!', body: 'Ready to focus?' },
};
const doneMessage = (timerType) => DONE_MESSAGES[timerType === 'pomodoro' ? 'pomodoro' : 'break'];

const requestNotificationPermission = async () => {
    if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission();
    }
};

const notifyTimerDone = (timerType) => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (document.visibilityState === 'visible') return;
    const { title, body } = doneMessage(timerType);
    new Notification(title, {
        body,
        icon: '/images/logo.webp',
        silent: false,
    });
};

export default {
  components: { ProjectSelect },
  props: {
    projects: {
      type: Array,
      required: true
    },
    settings: {
      type: Object,
      default: () => ({})
    },
    isAuthenticated: {
      type: [Boolean, Number],
      default: false
    },
    zenMode: {
      type: Boolean,
      default: false
    }
  },
  setup(props) {
    const { error } = useToast();
    const time = ref((props.settings?.timers?.settings?.pomodoro_duration ?? 25) * 60);
    const initialTime = ref(time.value);
    const alertVolume = ref(props.settings?.sound?.settings?.alert_volume ?? 50);
    const playSound = ref(props.settings?.sound?.settings?.play_sound ?? 'true');
    const isRunning = ref(false);
    const timerInterval = ref(null);
    const selectedTaskId = ref('');
    const selectedId = ref('');
    const sessionStartTime = ref(null);
    const currentTimerType = ref('pomodoro');
    const audio = ref(null);

    const completedPomodoros = ref(loadCompletedToday());

    const justFinished = ref(null); // timer type that just completed, until the next start
    const announcement = ref('');

    let isRestoring = false;

    watch(
      () => props.settings?.sound?.settings?.alert_volume,
      (newVolume) => {
        alertVolume.value = newVolume;
      }
    );

    watch(
      () => props.settings?.sound?.settings?.play_sound,
      (newValue) => {
        playSound.value = newValue;
      }
    );

    // Only react to the current timer's duration actually changing, and never
    // overwrite a timer that has already been started (running or paused).
    watch(
      () => props.settings?.timers?.settings?.[`${currentTimerType.value}_duration`],
      () => {
        if (!isRestoring && !isRunning.value && time.value === initialTime.value) {
          updateTimerFromSettings();
        }
      }
    );

    const formattedTime = computed(() => {
      const min = Math.floor(time.value / 60);
      const sec = time.value % 60;
      return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    });

    // Show the countdown in the tab title once a timer has been started.
    watchEffect(() => {
      if (justFinished.value) {
        document.title = `✓ ${doneMessage(justFinished.value).title} — ${BASE_TITLE}`;
        return;
      }
      const started = isRunning.value || time.value !== initialTime.value;
      if (!started) {
        document.title = BASE_TITLE;
        return;
      }
      const prefix = isRunning.value ? '' : '⏸ ';
      document.title = `${prefix}${formattedTime.value} · ${TIMER_LABELS[currentTimerType.value]} — ${BASE_TITLE}`;
    });

    // Mirror the timer in the user's Discord status (no-op outside Discord).
    watch([isRunning, currentTimerType], () => {
      setPresence(timerPresence({
        timerType: currentTimerType.value,
        isRunning: isRunning.value,
        isPaused: !isRunning.value && time.value > 0 && time.value !== initialTime.value,
        secondsLeft: time.value,
      }));
    }, { immediate: true });

    const selectedLabel = computed(() => {
      if (!selectedId.value) return '';
      for (const project of props.projects) {
        if (selectedId.value === 'project:rbNiqBehszLPVzMmR_' + project.id) return project.name;
        for (const task of project.tasks ?? []) {
          if (selectedId.value === 'task:rbNiqBehszLPVzMmR_' + task.id) {
            return `${project.name} › ${task.name}`;
          }
        }
      }
      return '';
    });

    const updateTimerFromSettings = () => {
      const duration = props.settings?.timers?.settings?.[`${currentTimerType.value}_duration`] ?? 25;
      time.value = duration * 60;
      initialTime.value = time.value;
    };

    const setTimer = (timerType) => {
      justFinished.value = null;
      if (sessionStartTime.value) endSession();
      currentTimerType.value = timerType;
      updateTimerFromSettings();
      clearInterval(timerInterval.value);
      isRunning.value = false;
      saveTimerStateToLocalStorage();
    };

    const toggleTimer = () => {
      if (isRunning.value) pauseTimer();
      else startTimer();
    };

    // Derive the remaining time from a fixed end timestamp: browsers throttle
    // timers in background tabs, so counting ticks drifts.
    let endTime = null;

    const tick = () => {
      time.value = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
      if (time.value <= 0) completeTimer(new Date(endTime));
    };

    const runInterval = () => {
      clearInterval(timerInterval.value);
      endTime = Date.now() + time.value * 1000;
      timerInterval.value = setInterval(tick, 250);
    };

    const completeTimer = (endedAt = new Date(), { silent = false } = {}) => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      time.value = 0;

      if (!silent) {
        if (isEnabled(playSound.value)) playAlarmSound();
        notifyTimerDone(currentTimerType.value);
        const { title, body } = doneMessage(currentTimerType.value);
        justFinished.value = currentTimerType.value;
        announcement.value = `${title} ${body}`;
      }
      if (sessionStartTime.value) endSession(endedAt);
      localStorage.removeItem('pomodoroTimer');
      const finishedType = currentTimerType.value;
      advanceCycle();

      if (!silent && finishedType !== 'pomodoro'
          && isEnabled(props.settings?.timers?.settings?.auto_start_pomodoros)) {
        startTimer();
        announcement.value = 'Break over! Next pomodoro started.';
      }
    };

    // After a pomodoro, line up a break (long every LONG_BREAK_INTERVAL);
    // after a break, line up the next pomodoro.
    const advanceCycle = () => {
      let next = 'pomodoro';
      if (currentTimerType.value === 'pomodoro') {
        completedPomodoros.value = loadCompletedToday() + 1;
        localStorage.setItem(CYCLE_KEY, JSON.stringify({
          date: toLocalDateString(new Date()),
          count: completedPomodoros.value,
        }));
        next = completedPomodoros.value % LONG_BREAK_INTERVAL === 0 ? 'long_break' : 'short_break';
      }
      currentTimerType.value = next;
      updateTimerFromSettings();
      saveTimerStateToLocalStorage();
    };

    // A pomodoro is one focus session, however many times it is paused.
    const startSession = () => {
      sessionStartTime.value = new Date();
      if (!props.isAuthenticated) return;

      const payload = {
        project_id: null,
        task_id: null,
        started_at: sessionStartTime.value
      };

      if (selectedId.value) {
        const selected = selectedId.value;
        if (selected.startsWith('project:rbNiqBehszLPVzMmR_')) {
          payload.project_id = extractAfterFirstUnderscore(selected);
        } else if (selected.startsWith('task:rbNiqBehszLPVzMmR_')) {
          payload.task_id = extractAfterFirstUnderscore(selected);
        }
      }

      axios
        .post('/focused-sessions', payload)
        .catch((err) => {
          const status = err.response?.status;
          if (status !== 401 && status !== 403) {
            error('Failed to start focus session');
          }
          console.error('Error starting session', err);
        });
    };

    const startTimer = () => {
      if (time.value <= 0) return;
      justFinished.value = null;
      announcement.value = '';
      if (currentTimerType.value === 'pomodoro' && !sessionStartTime.value) startSession();
      isRunning.value = true;
      requestNotificationPermission();
      runInterval();
      saveTimerStateToLocalStorage();
    };

    const pauseTimer = () => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      saveTimerStateToLocalStorage();
    };

    const resetTimer = () => {
      justFinished.value = null;
      clearInterval(timerInterval.value);
      isRunning.value = false;
      if (sessionStartTime.value) endSession();
      updateTimerFromSettings();
      saveTimerStateToLocalStorage();
    };

    const endSession = (endedAt = new Date()) => {
      const duration = initialTime.value - time.value;

      if (props.isAuthenticated) {
        axios
          .patch('/focused-sessions/current', { ended_at: endedAt, time_focused: duration })
          .catch((err) => { console.error('Error ending session', err); });
      } else if (duration > 0) {
        addLocalSession({
          date: toLocalDateString(endedAt),
          duration_seconds: duration,
          selectedId: selectedId.value,
        });
      }

      sessionStartTime.value = null;
      selectedTaskId.value = '';
    };

    const playAlarmSound = () => {
      const soundFile = props.settings?.sound?.settings?.alert_sound
        ? `${props.settings.sound.settings.alert_sound.toLowerCase()}.mp3`
        : 'waves.mp3';

      audio.value = new Audio(`/sounds/${soundFile}`);
      const volume = Math.min(Math.max(parseInt(alertVolume.value) / 100, 0), 1);
      audio.value.volume = volume;
      audio.value.play().catch((err) => {
        console.error('Error playing sound:', err);
      });
    };

    function extractAfterFirstUnderscore(str) {
      const index = str.indexOf('_');
      return index !== -1 ? str.slice(index + 1) : '';
    }

    // Persist running and paused timers so a reload resumes the same session.
    const saveTimerStateToLocalStorage = () => {
      if (!isRunning.value && !sessionStartTime.value && time.value === initialTime.value) {
        // Untouched timer: only remember a lined-up break across reloads.
        if (currentTimerType.value === 'pomodoro') localStorage.removeItem('pomodoroTimer');
        else localStorage.setItem('pomodoroTimer', JSON.stringify({ currentTimerType: currentTimerType.value }));
        return;
      }
      localStorage.setItem(
        'pomodoroTimer',
        JSON.stringify({
          isRunning: isRunning.value,
          endTime: isRunning.value ? endTime : null,
          remaining: time.value,
          initialTime: initialTime.value,
          currentTimerType: currentTimerType.value,
          sessionStartTime: sessionStartTime.value,
          selectedId: selectedId.value
        })
      );
    };

    watch(selectedId, () => {
      if (sessionStartTime.value) saveTimerStateToLocalStorage();
    });

    const restoreTimerState = (stored) => {
      if (stored.currentTimerType) currentTimerType.value = stored.currentTimerType;
      updateTimerFromSettings();
      if (stored.initialTime) initialTime.value = stored.initialTime;
      selectedId.value = stored.selectedId ?? '';

      if (stored.sessionStartTime) {
        sessionStartTime.value = new Date(stored.sessionStartTime);
      } else if (stored.isRunning && currentTimerType.value === 'pomodoro') {
        // Saved by an older version, which did not record the session start.
        sessionStartTime.value = new Date();
      }

      if (!stored.isRunning) {
        time.value = stored.remaining ?? initialTime.value;
        return;
      }

      const remainingSeconds = Math.ceil((stored.endTime - Date.now()) / 1000);
      if (remainingSeconds > 0) {
        time.value = remainingSeconds;
        isRunning.value = true;
        runInterval();
        endTime = stored.endTime;
      } else {
        // Finished while the page was closed: record it and line up the next timer.
        completeTimer(new Date(stored.endTime), { silent: true });
      }
    };

    onMounted(() => {
      isRestoring = true;
      try {
        const storedData = JSON.parse(localStorage.getItem('pomodoroTimer'));
        if (storedData) restoreTimerState(storedData);
        else updateTimerFromSettings();
      } catch {
        localStorage.removeItem('pomodoroTimer');
        updateTimerFromSettings();
      }
      isRestoring = false;
    });

    return {
      time,
      initialTime,
      isRunning,
      formattedTime,
      currentTimerType,
      selectedTaskId,
      selectedId,
      setTimer,
      toggleTimer,
      resetTimer,
      selectedLabel,
      justFinished,
      announcement,
      isDiscordActivity,
      projects: computed(() => props.projects),
      settings: computed(() => props.settings)
    };
  }
};
</script>

<style scoped>
.timer-button {
    padding: 0.5rem 1rem;
    border: 1px solid white;
    color: white;
    border-radius: 9999px;
    transition: all 0.2s;
}

.timer-button:focus {
    background-color: white;
    color: black;
}

.timer-button:hover {
    background-color: white;
    color: black;
}

.control-button {
    padding: 0.5rem 2rem;
    border-radius: 9999px;
    font-weight: 600;
}

.active-button {
    background-color: white;
    color: black;
}

/* Inside Discord the frame can be anything from a phone to a large window. */
.timer-fluid {
    font-size: clamp(3rem, min(26vw, 24vh), 8rem);
    line-height: 1;
}

.timer-done {
    animation: timer-done-pulse 1s ease-in-out 4;
}

@keyframes timer-done-pulse {
    50% {
        opacity: 0.35;
        transform: scale(1.04);
    }
}

@media (prefers-reduced-motion: reduce) {
    .timer-done {
        animation: none;
        text-decoration: underline;
        text-decoration-thickness: 4px;
        text-underline-offset: 12px;
    }
}
</style>
