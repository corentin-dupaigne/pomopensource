<template>
    <div class="flex flex-col items-center">
        <div class="flex space-x-4 mb-8" role="tablist" aria-label="Timer type">
            <button
                @click="setTimer('pomodoro', settings.timers.settings.pomodoro_duration)"
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
                @click="setTimer('short_break', settings.timers.settings.short_break_duration)"
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
                @click="setTimer('long_break', settings.timers.settings.long_break_duration)"
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
            class="text-9xl font-oswald font-bold mb-8"
            :aria-label="`Timer: ${formattedTime}`"
            aria-live="off"
        >
            {{ formattedTime }}
        </div>

        <div class="flex space-x-4 mb-8 cent">
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
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import ProjectSelect from './ProjectSelect.vue';

const requestNotificationPermission = async () => {
    if ('Notification' in window && Notification.permission === 'default') {
        await Notification.requestPermission();
    }
};

const notifyTimerDone = (timerType) => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (document.visibilityState === 'visible') return;
    const isPomodoro = timerType === 'pomodoro';
    new Notification(isPomodoro ? 'Pomodoro complete!' : 'Break over!', {
        body: isPomodoro ? 'Time to take a break.' : 'Ready to focus?',
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
    }
  },
  setup(props) {
    const { error } = useToast();
    const time = ref((props.settings?.timers?.settings?.pomodoro_duration ?? 25) * 60);
    const initialTime = ref(time.value);
    const alertVolume = ref(props.settings?.sound?.settings?.alert_volume ?? 50);
    const playSound = ref(props.settings?.sound?.settings?.play_sound ?? '0');
    const isRunning = ref(false);
    const timerInterval = ref(null);
    const selectedTaskId = ref('');
    const selectedId = ref('');
    const sessionStartTime = ref(null);
    const currentTimerType = ref('pomodoro');
    const audio = ref(null);

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

    watch(
      () => props.settings.timers.settings,
      () => {
        if (!isRestoring && !isRunning.value) {
          updateTimerFromSettings();
        }
      },
      { deep: true }
    );

    watch(time, () => {
      if (isRunning.value) {
        saveTimerStateToLocalStorage();
      }
    });

    const formattedTime = computed(() => {
      const min = Math.floor(time.value / 60);
      const sec = time.value % 60;
      return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`;
    });

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
      currentTimerType.value = timerType;
      updateTimerFromSettings();
      clearInterval(timerInterval.value);
      isRunning.value = false;
      localStorage.removeItem('pomodoroTimer');
    };

    const toggleTimer = () => {
      if (isRunning.value) pauseTimer();
      else startTimer();
    };

    const startTimer = () => {
      isRunning.value = true;
      sessionStartTime.value = new Date();
      requestNotificationPermission();

      timerInterval.value = setInterval(() => {
        time.value--;
        if (time.value <= 0) {
          clearInterval(timerInterval.value);
          isRunning.value = false;

          if (playSound.value === '1') playAlarmSound();
          notifyTimerDone(currentTimerType.value);
          if (currentTimerType.value === 'pomodoro') endSession();
        }
      }, 1000);

      saveTimerStateToLocalStorage();

      if (currentTimerType.value === 'pomodoro' && props.isAuthenticated) {
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
      }
    };

    const pauseTimer = () => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      saveTimerStateToLocalStorage();
    };

    const resetTimer = () => {
      clearInterval(timerInterval.value);
      isRunning.value = false;
      time.value = initialTime.value;

      if (sessionStartTime.value && currentTimerType.value === 'pomodoro') {
        endSession();
      }

      const duration = props.settings?.timers?.settings?.[`${currentTimerType.value}_duration`] ?? 25;
      time.value = duration * 60;
      initialTime.value = time.value;

      localStorage.removeItem('pomodoroTimer');
    };

    const endSession = () => {
      const payload = {
        ended_at: new Date(),
        time_focused: initialTime.value - time.value
      };

      axios
        .patch('/focused-sessions/current', payload)
        .catch((err) => {
          console.error('Error ending session', err);
        });

      sessionStartTime.value = null;
      selectedTaskId.value = '';
    };

    const playAlarmSound = () => {
      const soundFile = props.settings?.sound?.settings?.alert_sound
        ? `${props.settings.sound.settings.alert_sound.toLowerCase()}.mp3`
        : 'alarm.mp3';

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

    const saveTimerStateToLocalStorage = () => {
      if (isRunning.value) {
        const endTime = Date.now() + time.value * 1000;
        localStorage.setItem(
          'pomodoroTimer',
          JSON.stringify({
            isRunning: isRunning.value,
            endTime: endTime,
            currentTimerType: currentTimerType.value
          })
        );
      } else {
        localStorage.removeItem('pomodoroTimer');
      }
    };

    onMounted(() => {
      isRestoring = true;

      const storedData = localStorage.getItem('pomodoroTimer');
      if (!storedData) {
        updateTimerFromSettings();
        isRestoring = false;
        return;
      }

      if (storedData) {
        endSession();
      }

      const { isRunning: storedIsRunning, endTime, currentTimerType: storedTimerType } =
        JSON.parse(storedData);

      if (storedTimerType) {
        currentTimerType.value = storedTimerType;
      }

      const remainingSeconds = Math.floor((endTime - Date.now()) / 1000);

      if (remainingSeconds > 0) {
        time.value = remainingSeconds;
        initialTime.value = remainingSeconds;

        if (storedIsRunning) {
          startTimer();
        }
      } else {
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
</style>
