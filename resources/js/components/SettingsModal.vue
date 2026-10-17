<template>
    <div
        class="safe-area fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="settings-title"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content modal-surface flex flex-col w-full max-w-2xl mx-4 p-4 sm:p-6 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl transform transition-all duration-300 ease-in-out focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <div class="shrink-0 flex justify-between items-center pb-4 border-b border-white/20">
                <div class="flex items-center gap-3">
                    <h2 id="settings-title" class="text-2xl font-bold font-display text-white">Settings</h2>
                    <i v-if="isSaving" class="fas fa-circle-notch fa-spin text-white/40 text-sm" aria-label="Saving..."></i>
                </div>
                <button
                    @click="$emit('close')"
                    aria-label="Close settings"
                    class="text-white hover:text-gray-300 transition duration-150 ease-in-out"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <div class="flex-1 min-h-0 flex flex-col sm:flex-row mt-6 gap-4">
                <!-- Category sidebar — horizontal scroll on mobile, vertical on sm+ -->
                <div class="shrink-0 w-full sm:w-1/4 sm:pt-6">
                    <div class="flex sm:flex-col flex-row gap-2 overflow-x-auto pb-2 sm:pb-0">
                        <button
                            v-for="category in categories"
                            :key="category.name"
                            @click="activeCategory = category.name"
                            :aria-pressed="activeCategory === category.name"
                            :class="['py-2 px-4 text-left rounded-lg transition shrink-0 sm:w-full',
                                     activeCategory === category.name ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70 hover:bg-white/20']"
                        >
                            <i :class="category.icon" class="mr-2" aria-hidden="true"></i>{{ category.name }}
                        </button>
                    </div>
                </div>

                <!-- Settings content -->
                <div class="modal-body flex-1 min-h-0 w-full sm:w-3/4 sm:pl-4 pb-2">
                    <!-- The Discord call's timer, shared by everyone in it. -->
                    <div v-if="activeCategory === CALL_CATEGORY.name">
                        <p class="mb-6 text-sm text-white/60">
                            Everyone in the call shares this timer, and a change applies to everyone.
                            A timer already started keeps its time; the next one uses the new durations.
                        </p>
                        <div v-for="field in CALL_FIELDS" :key="field.type" class="mb-6">
                            <label :for="`call-${field.type}`" class="block text-sm font-medium text-white/70">{{ field.label }}</label>
                            <input
                                type="number"
                                min="1"
                                max="600"
                                :id="`call-${field.type}`"
                                :value="callTimer.durations?.[field.type]"
                                @change="setCallDuration(field.type, $event.target)"
                                class="mt-1 block w-1/4 bg-white/10 text-white border border-white/20 rounded-lg py-2 px-3 leading-5 focus:ring-white/40 focus:border-white/40 transition duration-150 ease-in-out"
                            >
                        </div>
                    </div>
                    <div v-else-if="activeCategory">
                        <p v-if="callTimer.active && activeCategory.toLowerCase() === 'timers'" class="mb-6 text-sm text-white/60">
                            Your durations start new calls. This call's timer has its own, under
                            <button type="button" class="underline hover:text-white" @click="activeCategory = CALL_CATEGORY.name">This call</button>.
                        </p>
                        <div v-for="setting in getActiveCategorySettings()" :key="setting.id" class="mb-6">

                            <label v-if="setting.type !== 'checkbox'" :for="setting.key" class="block text-sm font-medium text-white/70">
                                {{ setting.name }}
                            </label>

                            <input
                                v-if="setting.type === 'text'"
                                type="text"
                                v-model="setting.value"
                                :id="setting.key"
                                @input="debouncedSave(setting)"
                                class="mt-1 block w-full bg-white/10 text-white border border-white/20 rounded-lg py-2 px-3 leading-5 focus:ring-white/40 focus:border-white/40 transition duration-150 ease-in-out"
                            >

                            <!-- A sound plays once picked, and again from the button. -->
                            <div v-else-if="setting.type === 'select'" class="flex items-end gap-2">
                                <CustomSelect
                                    v-model="setting.value"
                                    :options="setting.options"
                                    :imageUrlFn="setting.key === 'theme' ? themeThumbnailUrl : null"
                                    :imageFallbackFn="setting.key === 'theme' ? themeImageUrl : null"
                                    @update:modelValue="saveSetting(setting); setting.key === 'alert_sound' && previewSound()"
                                    class="flex-1 min-w-0"
                                />
                                <button
                                    v-if="setting.key === 'alert_sound'"
                                    type="button"
                                    @click="previewSound"
                                    :aria-label="`Play ${setting.value}`"
                                    :title="`Play ${setting.value}`"
                                    class="shrink-0 w-10 h-10 flex items-center justify-center bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/15 transition"
                                >
                                    <i class="fas fa-play text-xs" aria-hidden="true"></i>
                                </button>
                            </div>

                            <div v-else-if="setting.type === 'checkbox'" class="mt-1 flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="isEnabled(setting.value)"
                                    @change="setting.value = $event.target.checked ? 1 : 0; saveSetting(setting)"
                                    :id="setting.key"
                                    class="toggle-input"
                                >
                                <label :for="setting.key" class="ml-2 text-white/70 cursor-pointer">{{ setting.name }}</label>
                            </div>

                            <div v-else-if="setting.type === 'number'" class="mt-1 flex flex-wrap gap-4">
                                <input
                                    type="number"
                                    v-model="setting.value"
                                    :id="setting.key"
                                    @change="saveSetting(setting)"
                                    class="block w-1/4 bg-white/10 text-white border border-white/20 rounded-lg py-2 px-3 leading-5 focus:ring-white/40 focus:border-white/40 transition duration-150 ease-in-out"
                                >
                            </div>

                            <div v-else-if="setting.type === 'range'" class="mt-4">
                                <div class="flex items-center mt-2">
                                    <span class="text-white/70 text-xs w-8 shrink-0">{{ setting.value }}</span>
                                    <input
                                        type="range"
                                        min="0"
                                        max="100"
                                        v-model="setting.value"
                                        :id="setting.key"
                                        :aria-label="`${setting.name}: ${setting.value}`"
                                        @input="debouncedSave(setting)"
                                        @change="setting.key === 'alert_volume' && previewSound()"
                                        class="range-slider ml-2 w-full cursor-pointer focus:outline-none"
                                        :style="{ '--pct': setting.value + '%' }"
                                    />
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import CustomSelect from './CustomSelect.vue';
import { isEnabled, settingsCategories as loadedCategories, alertSoundUrl, audioVolume } from '../composables/settings.js';
import { callTimer } from '../composables/callTimer.js';

// Shown first while in a Discord call: the call's own timer.
const CALL_CATEGORY = { name: 'This call', icon: 'fas fa-users' };
const CALL_FIELDS = [
    { type: 'pomodoro', label: 'Pomodoro (minutes)' },
    { type: 'short_break', label: 'Short break (minutes)' },
    { type: 'long_break', label: 'Long break (minutes)' },
];

const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
};

export default {
    components: { CustomSelect },
    emits: ['close', 'saved'],
    setup(_, { emit }) {
        const { error } = useToast();
        const settingsCategories = ref([]);
        const activeCategory = ref('');
        const isSaving = ref(false);
        const modalRef = ref(null);

        const categories = computed(() => callTimer.active
            ? [CALL_CATEGORY, ...settingsCategories.value]
            : settingsCategories.value);

        const showCategories = (loaded) => {
            settingsCategories.value = loaded;
            if (!activeCategory.value && categories.value.length > 0) activeCategory.value = categories.value[0].name;
        };
        if (callTimer.active) activeCategory.value = CALL_CATEGORY.name;

        const setCallDuration = (type, input) => {
            const minutes = parseInt(input.value);
            if (minutes >= 1 && minutes <= 600) {
                callTimer.setDurations({ [type]: minutes });
            } else {
                input.value = callTimer.durations?.[type] ?? '';
            }
        };

        const fetchSettings = async () => {
            try {
                const response = await axios.get('/user-settings');
                showCategories(response.data);
            } catch (err) {
                error('Failed to load settings');
            }
        };

        // A copy: a value edited but not saved (e.g. closed within the save
        // delay) must not still show the next time the modal opens.
        if (loadedCategories.value) showCategories(structuredClone(loadedCategories.value));

        const getActiveCategorySettings = () => {
            const category = settingsCategories.value.find(cat => cat.name === activeCategory.value);
            return category ? category.settings : [];
        };

        const saveSetting = async (setting) => {
            isSaving.value = true;
            try {
                await axios.patch('/user-settings', {
                    settings: [{ id: setting.id, value: setting.value }],
                });
                emit('saved');
            } catch (err) {
                error('Failed to save setting');
            } finally {
                isSaving.value = false;
            }
        };

        const debouncedSave = debounce(saveSetting, 500);

        onMounted(async () => {
            // Only before the page has loaded them, e.g. opened right away.
            if (!loadedCategories.value) await fetchSettings();
            await nextTick();
            modalRef.value?.focus();
        });

        // Plays the chosen alert sound at the chosen volume, from the start
        // if it is already playing.
        const preview = new Audio();
        const settingValue = (key) => settingsCategories.value
            .flatMap((category) => category.settings)
            .find((setting) => setting.key === key)?.value;
        const previewSound = () => {
            const url = alertSoundUrl(settingValue('alert_sound'));
            if (!preview.src.endsWith(url)) preview.src = url;
            preview.currentTime = 0;
            preview.volume = audioVolume(settingValue('alert_volume') ?? 50);
            preview.play().catch(() => {});
        };
        onUnmounted(() => preview.pause());

        // The picker shows small thumbnails: the backgrounds themselves weigh
        // up to 1.5MB each.
        const themeFile = (theme) => `${theme.toLowerCase().replace(/ /g, '_')}.webp`;
        const themeThumbnailUrl = (theme) => `images/backgrounds/thumbnails/${themeFile(theme)}`;
        const themeImageUrl = (theme) => `images/backgrounds/${themeFile(theme)}`;

        return {
            settingsCategories,
            categories,
            callTimer,
            CALL_CATEGORY,
            CALL_FIELDS,
            setCallDuration,
            activeCategory,
            isSaving,
            modalRef,
            getActiveCategorySettings,
            saveSetting,
            debouncedSave,
            themeThumbnailUrl,
            previewSound,
            themeImageUrl,
            isEnabled,
        };
    },
};
</script>

<style scoped>
.modal-content {
    /* Fit the frame, however short: a Discord Activity can be under 400px tall. */
    max-height: min(90vh, calc(100% - 2rem));
    overflow: hidden;
}

.modal-body {
    overflow-y: auto;
}

.modal-body::-webkit-scrollbar {
    width: 8px;
}

.modal-body::-webkit-scrollbar-thumb {
    background-color: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
}

.modal-body::-webkit-scrollbar-track {
    background: transparent;
}

.toggle-input {
    appearance: none;
    width: 48px;
    height: 24px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 9999px;
    position: relative;
    outline: none;
    transition: background 0.3s, box-shadow 0.3s;
    cursor: pointer;
    box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.2);
    flex-shrink: 0;
}

.toggle-input:checked {
    background: rgba(255, 255, 255, 0.5);
}

.toggle-input::after {
    content: "";
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background: white;
    border-radius: 50%;
    transition: transform 0.3s, box-shadow 0.3s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.toggle-input:checked::after {
    transform: translateX(24px);
}

.toggle-input:hover::after {
    box-shadow: 0 1px 5px rgba(0, 0, 0, 0.5);
}

.toggle-input:focus {
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.3);
}

.range-slider {
    appearance: none;
    height: 4px;
    border-radius: 9999px;
    background: linear-gradient(
        to right,
        rgba(255, 255, 255, 0.8) var(--pct, 50%),
        rgba(255, 255, 255, 0.2) var(--pct, 50%)
    );
    outline: none;
}

.range-slider::-webkit-slider-thumb {
    appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: white;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
    cursor: pointer;
    transition: transform 0.15s, box-shadow 0.15s;
}

.range-slider::-webkit-slider-thumb:hover {
    transform: scale(1.2);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
}

.range-slider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border: none;
    border-radius: 50%;
    background: white;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
    cursor: pointer;
}

.range-slider::-moz-range-track {
    height: 4px;
    border-radius: 9999px;
    background: rgba(255, 255, 255, 0.2);
}

.range-slider:focus::-webkit-slider-thumb {
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.25), 0 1px 4px rgba(0, 0, 0, 0.4);
}
</style>
