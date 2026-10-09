<template>
    <div class="flex flex-col items-center">
        <div class="quiet-fade hide-when-minimal flex flex-wrap justify-center gap-2 sm:gap-4 px-4 mb-8 short:mb-2" role="tablist" aria-label="Timer type">
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

        <!-- Picture-in-picture and grid tiles hide the tabs: name the timer. -->
        <p class="show-when-minimal text-sm uppercase tracking-widest font-inter font-semibold text-white/80 mb-1">
            {{ TIMER_LABELS[currentTimerType] }}<template v-if="!isRunning && time !== initialTime && time > 0"> · paused</template>
        </p>

        <div
            id="timerDisplay"
            class="text-9xl font-oswald font-bold mb-8 short:text-7xl short:mb-2"
            :class="{ 'timer-done': justFinished, 'timer-fluid': isDiscordActivity }"
            :aria-label="`Timer: ${formattedTime}`"
            aria-live="off"
        >
            {{ formattedTime }}
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <div class="show-when-minimal w-40 h-1.5 mt-2 rounded-full bg-white/20 overflow-hidden" aria-hidden="true">
            <div
                class="h-full rounded-full transition-[width] duration-300"
                :class="currentTimerType === 'pomodoro' ? 'bg-[var(--discord-blurple)]' : 'bg-emerald-400'"
                :style="{ width: `${progress * 100}%` }"
            ></div>
        </div>

        <!-- Tiles often don't take clicks reliably, so they only show the time. -->
        <div class="hide-when-minimal flex items-center space-x-4 mb-8 short:mb-2">
            <button
                @click="toggleTimer"
                id="stop-start-button"
                class="control-button bg-white text-black border-2 border-transparent hover:bg-transparent hover:text-white hover:border-2 hover:border-white"
                :aria-label="isRunning ? 'Pause timer' : 'Start timer'"
            >
                {{ isRunning ? 'pause' : 'start' }}
            </button>
            <button
                @click="requestReset"
                class="reset-button text-2xl"
                aria-label="Reset timer"
            >
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
            </button>
        </div>

        <ConfirmModal
            :visible="confirmReset"
            title="Reset timer"
            :message="shared
                ? 'This resets the timer for everyone in the call. Your time so far is saved.'
                : 'The time spent so far is saved, and the timer starts over.'"
            confirmLabel="Reset"
            @confirm="confirmReset = false; resetTimer()"
            @cancel="confirmReset = false"
        />

        <!-- What this pomodoro is for: the project and a note, as one bar. In
             the Activity's smaller frame it keeps to about the tabs' width.
             The note is kept from one pomodoro to the next: a long stretch on
             the same thing should not mean typing it again. -->
        <div
            v-if="currentTimerType === 'pomodoro' && !isRunning"
            class="hide-when-minimal mb-4 flex items-stretch max-w-[calc(100vw-2rem)] rounded-lg bg-white/10 backdrop-blur-sm border border-white/30 focus-within:border-white/70 transition-colors"
            :class="isDiscordActivity ? 'w-80' : 'w-[28rem]'"
        >
            <ProjectSelect
                v-model="selectedId"
                :projects="projects"
                :createProject="createProject"
                embedded
                @manage="$emit('manageProjects')"
            />
            <span class="w-px my-2 bg-white/20" aria-hidden="true"></span>
            <input
                v-model="note"
                @keydown.enter="$event.target.blur()"
                type="text"
                maxlength="255"
                placeholder="What are you working on?"
                aria-label="What are you working on? (optional note for this session)"
                class="flex-1 min-w-0 bg-transparent border-0 px-3 py-2.5 short:py-2 text-sm text-white placeholder-white/60 focus:outline-none focus:ring-0"
            >
        </div>

        <!-- While it runs: the same information as quiet text. The project is
             locked; the note can still be edited. -->
        <div
            v-else-if="currentTimerType === 'pomodoro'"
            class="quiet-fade hide-when-minimal mb-4 flex items-center justify-center gap-2 max-w-[calc(100vw-2rem)] text-sm text-white/70"
        >
            <i :class="selectedId ? 'fas fa-folder' : 'fas fa-infinity'" class="text-white/40 text-xs" aria-hidden="true"></i>
            <span class="truncate">{{ selectedLabel || 'General focus' }}</span>
            <span class="text-white/30" aria-hidden="true">·</span>
            <label class="group flex items-center gap-1.5 min-w-0 cursor-text">
                <input
                    v-model="note"
                    @keydown.enter="$event.target.blur()"
                    type="text"
                    maxlength="255"
                    :size="Math.min(Math.max(note.length, 10), 32)"
                    placeholder="Add a note"
                    aria-label="Note for this session"
                    class="min-w-0 [field-sizing:content] bg-transparent border-0 border-b border-transparent p-0 text-sm text-white placeholder-white/50 focus:outline-none focus:ring-0 focus:border-white/50 group-hover:border-white/30 transition-colors"
                >
                <i class="fas fa-pencil-alt text-[10px] text-white/40 group-hover:text-white/70" aria-hidden="true"></i>
            </label>
        </div>
    </div>
</template>

<script>
import { ref, computed, watch, watchEffect, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import { addLocalSession, toLocalDateString } from '../composables/localStats.js';
import { isEnabled, settingsSaved } from '../composables/settings.js';
import { useSharedTimer } from '../composables/sharedTimer.js';
import { timerRunning } from '../composables/focus.js';
import { setPresence, timerPresence, isDiscordActivity, discordInstanceId } from '../discord.js';
import ProjectSelect from './ProjectSelect.vue';
import ConfirmModal from './ConfirmModal.vue';

const BASE_TITLE = 'Pomopensource';
const TIMER_LABELS = {
    pomodoro: 'Focus',
    short_break: 'Short break',
    long_break: 'Long break',
};

// In the Activity, one saved timer per call: joining another call must not
// resume the previous call's timer. Timers of earlier calls are dropped.
const TIMER_KEY_PREFIX = 'pomodoroTimer';
const TIMER_KEY = discordInstanceId ? `${TIMER_KEY_PREFIX}:${discordInstanceId}` : TIMER_KEY_PREFIX;
if (discordInstanceId) {
    try {
        Object.keys(localStorage)
            .filter((key) => key.startsWith(`${TIMER_KEY_PREFIX}:`) && key !== TIMER_KEY)
            .forEach((key) => localStorage.removeItem(key));
    } catch {
        // Storage unavailable: nothing to clean up.
    }
}

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

// Browsers block notifications in cross-origin iframes such as Discord's.
const canNotify = () => !isDiscordActivity && 'Notification' in window;

const requestNotificationPermission = async () => {
    if (canNotify() && Notification.permission === 'default') {
        await Notification.requestPermission();
    }
};

const notifyTimerDone = (timerType) => {
    if (!canNotify() || Notification.permission !== 'granted') return;
    if (document.visibilityState === 'visible') return;
    const { title, body } = doneMessage(timerType);
    new Notification(title, {
        body,
        icon: '/images/logo.webp',
        silent: false,
    });
};

export default {
  components: { ProjectSelect, ConfirmModal },
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
    createProject: {
      type: Function,
      default: null
    }
  },
  emits: ['manageProjects'],
  setup(props) {
    const { error } = useToast();
    const time = ref((props.settings?.timers?.settings?.pomodoro_duration ?? 25) * 60);
    const initialTime = ref(time.value);
    const alertVolume = ref(props.settings?.sound?.settings?.alert_volume ?? 50);
    const playSound = ref(props.settings?.sound?.settings?.play_sound ?? 'true');
    const isRunning = ref(false);
    // Lets the page go quiet while the timer runs (see composables/focus.js).
    watch(isRunning, (running) => { timerRunning.value = running; });
    onUnmounted(() => { timerRunning.value = false; });
    const timerInterval = ref(null);
    const selectedId = ref('');
    const note = ref('');
    const sessionStartTime = ref(null);
    const currentTimerType = ref('pomodoro');
    const audio = ref(null);

    const completedPomodoros = ref(loadCompletedToday());

    const justFinished = ref(null); // timer type that just completed, until the next start
    const announcement = ref('');

    let isRestoring = false;

    // In a Discord call, everyone signed in shares one timer: actions go to
    // the server, and its state drives this one (see applySharedState).
    const shared = Boolean(isDiscordActivity && discordInstanceId && props.isAuthenticated);

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
        if (!shared && !isRestoring && !isRunning.value && time.value === initialTime.value) {
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

    const progress = computed(() => initialTime.value > 0 ? 1 - time.value / initialTime.value : 0);

    const selectedLabel = computed(() => {
      if (!selectedId.value) return '';
      const project = props.projects.find((p) => selectedId.value === 'project:rbNiqBehszLPVzMmR_' + p.id);
      return project?.name ?? '';
    });

    const updateTimerFromSettings = () => {
      const duration = props.settings?.timers?.settings?.[`${currentTimerType.value}_duration`] ?? 25;
      time.value = duration * 60;
      initialTime.value = time.value;
    };

    // Minutes per timer type from this user's settings, sent with shared
    // actions so the room uses them.
    const durationsFromSettings = () => Object.fromEntries(
      ['pomodoro', 'short_break', 'long_break']
        .map((type) => [type, parseInt(props.settings?.timers?.settings?.[`${type}_duration`])])
        .filter(([, minutes]) => minutes > 0)
    );

    const setTimer = (timerType) => {
      if (shared) {
        sharedTimer.send('switch', { timer_type: timerType, durations: durationsFromSettings() });
        return;
      }
      justFinished.value = null;
      if (sessionStartTime.value) endSession();
      currentTimerType.value = timerType;
      updateTimerFromSettings();
      clearInterval(timerInterval.value);
      isRunning.value = false;
      saveTimerStateToLocalStorage();
    };

    const toggleTimer = () => {
      if (!isRunning.value && isEnabled(playSound.value)) unlockAlarmSound();
      if (shared) {
        sharedTimer.send(isRunning.value ? 'pause' : 'start', { durations: durationsFromSettings() });
      } else if (isRunning.value) {
        pauseTimer();
      } else {
        startTimer();
      }
    };

    // Derive the remaining time from a fixed end timestamp: browsers throttle
    // timers in background tabs, so counting ticks drifts.
    let endTime = null;

    const tick = () => {
      time.value = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
      if (time.value > 0) return;
      if (shared) completeSharedTimer();
      else completeTimer(new Date(endTime));
    };

    const runInterval = () => {
      clearInterval(timerInterval.value);
      endTime = Date.now() + time.value * 1000;
      timerInterval.value = setInterval(tick, 250);
    };

    const announceDone = (timerType) => {
      if (isEnabled(playSound.value)) playAlarmSound();
      notifyTimerDone(timerType);
      const { title, body } = doneMessage(timerType);
      justFinished.value = timerType;
      announcement.value = `${title} ${body}`;
    };

    const completeTimer = (endedAt = new Date(), { silent = false } = {}) => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      time.value = 0;

      if (!silent) announceDone(currentTimerType.value);
      if (sessionStartTime.value) endSession(endedAt);
      localStorage.removeItem(TIMER_KEY);
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
    // Time left when it started: in a shared timer, someone joining halfway
    // through only counts the part they were there for.
    let sessionStartRemaining = null;
    const startSession = () => {
      sessionStartTime.value = new Date();
      sessionStartRemaining = time.value;
      if (!props.isAuthenticated) return;

      const payload = {
        project_id: selectedId.value.startsWith('project:rbNiqBehszLPVzMmR_')
          ? extractAfterFirstUnderscore(selectedId.value)
          : null,
        started_at: sessionStartTime.value
      };

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
      if (shared) {
        sharedTimer.send('reset', { durations: durationsFromSettings() });
        return;
      }
      justFinished.value = null;
      clearInterval(timerInterval.value);
      isRunning.value = false;
      if (sessionStartTime.value) endSession();
      updateTimerFromSettings();
      saveTimerStateToLocalStorage();
    };

    // Reset sits next to start: ask first once there is progress to lose.
    const confirmReset = ref(false);
    const requestReset = () => {
      if (isRunning.value || time.value !== initialTime.value) confirmReset.value = true;
      else resetTimer();
    };

    const endSession = (endedAt = new Date()) => {
      const duration = (sessionStartRemaining ?? initialTime.value) - time.value;
      sessionStartRemaining = null;

      const selected = selectedId.value;
      const sessionNote = note.value.trim() || null;
      const startedAt = sessionStartTime.value;
      const saveLocally = () => {
        if (duration <= 0) return;
        addLocalSession({
          date: toLocalDateString(endedAt),
          started_at: startedAt ? new Date(startedAt).toISOString() : null,
          duration_seconds: duration,
          selectedId: selected,
          note: sessionNote,
        });
      };

      if (props.isAuthenticated) {
        axios
          .patch('/focused-sessions/current', { ended_at: endedAt, time_focused: duration, note: sessionNote })
          .catch((err) => {
            // Keep the time on this device rather than losing it.
            console.error('Error ending session', err);
            saveLocally();
          });
      } else {
        saveLocally();
      }

      sessionStartTime.value = null;
    };

    const alarmSoundUrl = () => {
      const soundFile = props.settings?.sound?.settings?.alert_sound
        ? `${props.settings.sound.settings.alert_sound.toLowerCase()}.mp3`
        : 'waves.mp3';
      return `/sounds/${soundFile}`;
    };

    // Mobile webviews, Discord's included, only let an audio element play
    // after it has played during a tap. The alarm fires later, from a timer,
    // so the element is unlocked silently when start is tapped and reused.
    const unlockAlarmSound = () => {
      if (!audio.value) audio.value = new Audio();
      const element = audio.value;
      element.src = alarmSoundUrl();
      element.muted = true;
      element.play()
        .then(() => {
          element.pause();
          element.currentTime = 0;
          element.muted = false;
        })
        .catch(() => { element.muted = false; });
    };

    const playAlarmSound = () => {
      if (!audio.value) audio.value = new Audio();
      const element = audio.value;
      const url = alarmSoundUrl();
      if (!element.src.endsWith(url)) element.src = url;
      element.muted = false;
      element.currentTime = 0;
      element.volume = Math.min(Math.max(parseInt(alertVolume.value) / 100, 0), 1);
      element.play().catch((err) => {
        console.error('Error playing sound:', err);
      });
    };

    function extractAfterFirstUnderscore(str) {
      const index = str.indexOf('_');
      return index !== -1 ? str.slice(index + 1) : '';
    }

    // Persist running and paused timers so a reload resumes the same session.
    // A shared timer lives on the server instead.
    const saveTimerStateToLocalStorage = () => {
      if (shared) return;
      if (!isRunning.value && !sessionStartTime.value && time.value === initialTime.value) {
        // Untouched timer: only remember a lined-up break across reloads.
        if (currentTimerType.value === 'pomodoro') localStorage.removeItem(TIMER_KEY);
        else localStorage.setItem(TIMER_KEY, JSON.stringify({ currentTimerType: currentTimerType.value }));
        return;
      }
      localStorage.setItem(
        TIMER_KEY,
        JSON.stringify({
          isRunning: isRunning.value,
          endTime: isRunning.value ? endTime : null,
          remaining: time.value,
          initialTime: initialTime.value,
          currentTimerType: currentTimerType.value,
          sessionStartTime: sessionStartTime.value,
          selectedId: selectedId.value,
          note: note.value
        })
      );
    };

    // A note is about one project: picking another starts a blank one.
    // Synchronous, so restoring a saved project and its note keeps the note.
    watch(selectedId, () => { note.value = ''; }, { flush: 'sync' });

    watch([selectedId, note], () => {
      if (sessionStartTime.value) saveTimerStateToLocalStorage();
    });

    const restoreTimerState = (stored) => {
      if (stored.currentTimerType) currentTimerType.value = stored.currentTimerType;
      updateTimerFromSettings();
      if (stored.initialTime) initialTime.value = stored.initialTime;
      // Tasks were folded into their projects: a saved task is no longer picked.
      selectedId.value = stored.selectedId?.startsWith('project:') ? stored.selectedId : '';
      note.value = stored.note ?? '';

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

    // The shared timer ran out here first: tell everyone now rather than
    // waiting for the server to notice, and skip the effects when the
    // server's "complete" comes back.
    let completedHere = false;
    const completeSharedTimer = () => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      time.value = 0;
      // The server may answer that it is not over yet (clock skew): then this
      // runs again a moment later, without repeating the effects.
      if (!completedHere) {
        completedHere = true;
        announceDone(currentTimerType.value);
        if (sessionStartTime.value) endSession(new Date(endTime));
      }
      sharedTimer.send('complete');
    };

    let firstSharedState = true;
    const applySharedState = (state, { isNew, offset }) => {
      const joining = firstSharedState;
      firstSharedState = false;

      if (isNew) {
        if (state.event === 'complete') {
          // Someone else's device saw it end first.
          if (!completedHere && !joining) {
            time.value = 0;
            announceDone(state.completed_type);
            if (sessionStartTime.value) endSession(new Date(state.completed_at - offset));
          }
        } else if (!joining && state.status !== 'running') {
          justFinished.value = null;
        }
        completedHere = false;
      }

      // This user's focus session follows the shared pomodoro: it starts when
      // the pomodoro runs, and ends when it is reset or switched away.
      const pomodoroOn = state.timer_type === 'pomodoro' && state.status !== 'idle';
      if (sessionStartTime.value && !pomodoroOn) endSession();

      clearInterval(timerInterval.value);
      currentTimerType.value = state.timer_type;
      initialTime.value = state.duration;
      if (state.status === 'running') {
        endTime = state.ends_at - offset;
        time.value = Math.max(0, Math.ceil((endTime - Date.now()) / 1000));
        isRunning.value = true;
        timerInterval.value = setInterval(tick, 250);
        if (justFinished.value && isNew) justFinished.value = null;
      } else {
        time.value = state.remaining;
        isRunning.value = false;
      }

      if (state.status === 'running' && state.timer_type === 'pomodoro' && !sessionStartTime.value && !completedHere) {
        startSession();
      }

      // Nobody has touched this call's timer yet: line it up with the
      // durations of whoever opens it first.
      const durations = durationsFromSettings();
      if (joining && state.version === 0 && Object.keys(durations).length > 0) {
        sharedTimer.send('reset', { durations });
      }
    };

    const sharedTimer = shared ? useSharedTimer(discordInstanceId, applySharedState) : null;

    // A shared timer only takes durations with an action: after the user
    // saves new durations, line up the call's timer again, unless it has been
    // started (as the solo timer never overwrites a started timer either).
    if (shared) {
      let savedDurations = JSON.stringify(durationsFromSettings());
      watch(settingsSaved, () => {
        const durations = durationsFromSettings();
        const changed = JSON.stringify(durations) !== savedDurations;
        savedDurations = JSON.stringify(durations);
        if (changed && !isRunning.value && time.value === initialTime.value) {
          sharedTimer.send('reset', { durations });
        }
      });
    }
    onUnmounted(() => {
      sharedTimer?.stop();
      clearInterval(timerInterval.value);
    });

    onMounted(() => {
      if (shared) {
        sharedTimer.start();
        return;
      }
      isRestoring = true;
      try {
        const storedData = JSON.parse(localStorage.getItem(TIMER_KEY));
        if (storedData) restoreTimerState(storedData);
        else updateTimerFromSettings();
      } catch {
        localStorage.removeItem(TIMER_KEY);
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
      selectedId,
      note,
      setTimer,
      toggleTimer,
      resetTimer,
      confirmReset,
      requestReset,
      shared,
      selectedLabel,
      justFinished,
      announcement,
      progress,
      TIMER_LABELS,
      isDiscordActivity,
      projects: computed(() => props.projects),
      settings: computed(() => props.settings)
    };
  }
};
</script>

<style scoped>
.timer-button {
    padding: 0.5rem 0.875rem;
    border: 1px solid white;
    color: white;
    border-radius: 9999px;
    transition: all 0.2s;
}

/* Only the selected tab is filled: on touch screens focus stays on the
   last tapped button, which then looked selected too. */
.timer-button:focus-visible {
    outline: 2px solid white;
    outline-offset: 2px;
}

.timer-button:disabled {
    cursor: not-allowed;
}

.timer-button:disabled:not(.active-button) {
    opacity: 0.4;
}

@media (hover: hover) {
    .timer-button:not(:disabled):hover {
        background-color: white;
        color: black;
    }
}

@media (min-width: 640px) {
    .timer-button {
        padding: 0.5rem 1rem;
    }
}

.control-button {
    padding: 0.5rem 2rem;
    border-radius: 9999px;
    font-weight: 600;
}

/* Landscape phones and Discord's call view: every pixel of height counts. */
@media (max-height: 500px) {
    .timer-button {
        padding: 0.25rem 0.75rem;
        font-size: 0.875rem;
    }

    .control-button {
        padding: 0.375rem 1.75rem;
    }
}

.reset-button {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 2.75rem;
    min-height: 2.75rem;
    border-radius: 9999px;
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
