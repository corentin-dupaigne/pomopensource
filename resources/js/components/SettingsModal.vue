<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50"
        role="dialog"
        aria-modal="true"
        aria-labelledby="settings-title"
        @click.self="$emit('close')"
    >
        <div
            ref="modalRef"
            tabindex="-1"
            class="modal-content w-full max-w-2xl p-6 bg-white/10 backdrop-blur-lg rounded-lg shadow-xl transform transition-all duration-300 ease-in-out focus:outline-none"
            @keydown.esc="$emit('close')"
        >
            <div class="flex justify-between items-center pb-4 border-b border-white/20">
                <h2 id="settings-title" class="text-2xl font-bold font-oswald text-white">Settings</h2>
                <button
                    @click="$emit('close')"
                    aria-label="Close settings"
                    class="text-white hover:text-gray-300 transition duration-150 ease-in-out"
                >
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <div class="flex flex-col sm:flex-row mb-6 mt-6 gap-4">
                <!-- Category sidebar — horizontal scroll on mobile, vertical on sm+ -->
                <div class="w-full sm:w-1/4 sm:pt-6">
                    <div class="flex sm:flex-col flex-row gap-2 overflow-x-auto pb-2 sm:pb-0">
                        <button
                            v-for="category in settingsCategories"
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
                <div class="modal-body w-full sm:w-3/4 sm:pl-4">
                    <div v-if="activeCategory">
                        <div v-for="setting in getActiveCategorySettings()" :key="setting.id" class="mb-6">

                            <label v-if="setting.type !== 'checkbox'" :for="setting.key" class="block text-sm font-medium text-white/70">
                                {{ setting.name }}
                            </label>

                            <input
                                v-if="setting.type === 'text'"
                                type="text"
                                v-model="setting.value"
                                :id="setting.key"
                                class="mt-1 block w-full bg-white/10 text-white border border-white/20 rounded-lg py-2 px-3 leading-5 focus:ring-white/40 focus:border-white/40 transition duration-150 ease-in-out"
                            >

                            <CustomSelect
                                v-else-if="setting.type === 'select'"
                                v-model="setting.value"
                                :options="setting.options"
                            />

                            <div v-else-if="setting.type === 'checkbox'" class="mt-1 flex items-center">
                                <input
                                    type="checkbox"
                                    :checked="setting.value === '1' || setting.value === 1 || setting.value === true"
                                    @change="setting.value = $event.target.checked ? 1 : 0"
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
                                        class="range-slider ml-2 w-full cursor-pointer focus:outline-none"
                                        :style="{ '--pct': setting.value + '%' }"
                                    />
                                </div>

                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer pt-4 border-t border-white/20 flex justify-end gap-2">
                <button
                    @click="saveSettings"
                    :disabled="isSaving"
                    class="px-4 py-2 bg-blue-800/50 text-white rounded-lg hover:bg-blue-900/50 transition disabled:opacity-50 flex items-center gap-2"
                >
                    <i v-if="isSaving" class="fas fa-spinner fa-spin" aria-hidden="true"></i>
                    <span>{{ isSaving ? 'Saving...' : 'Save' }}</span>
                </button>
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 bg-red-800/50 text-white rounded-lg hover:bg-red-900/50 transition"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { useToast } from '../composables/toast.js';
import CustomSelect from './CustomSelect.vue';

export default {
    components: { CustomSelect },
    emits: ['close', 'saved'],
    setup(_, { emit }) {
        const { success, error } = useToast();
        const settingsCategories = ref([]);
        const activeCategory = ref('');
        const isSaving = ref(false);
        const modalRef = ref(null);

        const fetchSettings = async () => {
            try {
                const response = await axios.get('/user-settings');
                settingsCategories.value = response.data;
                if (settingsCategories.value.length > 0) {
                    activeCategory.value = settingsCategories.value[0].name;
                }
            } catch (err) {
                error('Failed to load settings');
            }
        };

        const getActiveCategorySettings = () => {
            const category = settingsCategories.value.find(cat => cat.name === activeCategory.value);
            return category ? category.settings : [];
        };

        const saveSettings = async () => {
            isSaving.value = true;
            try {
                const payload = {
                    settings: settingsCategories.value.flatMap(category =>
                        category.settings.map(setting => ({
                            id: setting.id,
                            value: setting.value,
                        }))
                    ),
                };
                await axios.patch('/user-settings', payload);
                success('Settings saved');
                emit('saved');
                emit('close');
            } catch (err) {
                error('Failed to save settings');
            } finally {
                isSaving.value = false;
            }
        };

        onMounted(async () => {
            await fetchSettings();
            await nextTick();
            modalRef.value?.focus();
        });

        return {
            settingsCategories,
            activeCategory,
            isSaving,
            modalRef,
            getActiveCategorySettings,
            saveSettings,
        };
    },
};
</script>

<style scoped>
.modal-content {
    max-height: 90vh;
    overflow: hidden;
}

.modal-body {
    max-height: calc(90vh - 12rem);
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
    background: rgba(37, 99, 235, 0.5);
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
