<template>
    <!-- Inside Discord the app fills the frame exactly: no page scroll. -->
    <div class="app safe-area flex flex-col" :class="isDiscordActivity ? ['activity h-[100dvh] overflow-hidden', { minimal: isSmallLayout(discordLayoutMode) }] : 'min-h-screen'">
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'a' }" :style="{ backgroundImage: bgA }"></div>
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'b' }" :style="{ backgroundImage: bgB }"></div>
        <div class="background-overlay" :class="{ 'overlay-zen': zenMode }"></div>

        <div class="header zen-fade hide-when-minimal" :class="{ 'zen-hidden': zenMode }">
            <Header @toggleStats="toggleStatsModal" @toggle-settings="toggleSettingsModal" @toggle-projects="showProjectsPanel = !showProjectsPanel" :auth="isAuthenticated" />
        </div>

        <main class="flex-1 flex flex-col items-center justify-center text-white main-content">
            <ProjectsAndTasks
                :projects="projects"
                :settings="settings"
                :isAuthenticated="isAuthenticated"
                :zenMode="zenMode"
                :asPanel="isDiscordActivity"
                :panelOpen="showProjectsPanel"
                @closePanel="showProjectsPanel = false"
            />
        </main>

        <!-- Zen toggle: always bottom-right -->
        <button
            @click="toggleZen"
            :aria-label="zenMode ? 'Exit zen mode' : 'Enter zen mode'"
            class="zen-toggle hide-when-minimal fixed z-10 flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
        >
            <i :class="zenMode ? 'fas fa-compress' : 'fas fa-expand'" aria-hidden="true"></i>
            <span v-if="!zenMode" class="text-sm font-inter">zen</span>
        </button>

        <StatsModal v-if="showStatsModal" :isAuthenticated="isAuthenticated" @close="toggleStatsModal" />
        <SettingsModal v-if="showSettingsModal" @close="toggleSettingsModal" @saved="handleSettingsSaved" />
        <Toast />
    </div>
</template>

<script>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import Header from '../components/Header.vue';
import ProjectsAndTasks from '../components/ProjectsAndTasks.vue';
import Footer from '../components/Footer.vue';
import StatsModal from '../components/StatsModal.vue';
import SettingsModal from '../components/SettingsModal.vue';
import Toast from '../components/Toast.vue';
import axios from 'axios';
import { startDiscordActivity, isDiscordActivity, discordLayoutMode, isSmallLayout } from '../discord.js';

export default {
    components: {
        Header,
        ProjectsAndTasks,
        Footer,
        StatsModal,
        SettingsModal,
        Toast,
    },
    props: {
        projects: {
            type: Array,
            default: () => []
        },
        isAuthenticated: {
            type: Boolean,
            default: false
        },
        discordClientId: {
            type: String,
            default: null
        },
    },
    setup(props) {
        const showStatsModal = ref(false);
        const showSettingsModal = ref(false);
        const showProjectsPanel = ref(false);
        const zenMode = ref(false);

        const toggleZen = () => { zenMode.value = !zenMode.value; };
        const backgroundImage = ref(localStorage.getItem('userBackground') || '');
        const isAuthenticated = ref(props.isAuthenticated);
        const settings = ref({});

        const initialBg = backgroundImage.value ? `url(${backgroundImage.value})` : '';
        const bgA = ref(initialBg);
        const bgB = ref('');
        const activeLayer = ref('a');

        const fetchSettings = async () => {
            try {
                const response = await axios.get('/user-settings');
                const rawData = response.data;

                const transformedData = Array.from(rawData).reduce((acc, category) => {
                    acc[category.name.toLowerCase()] = {
                        icon: category.icon,
                        settings: Array.from(category.settings).reduce((settingAcc, setting) => {
                            settingAcc[setting.key.toLowerCase()] = setting.value;
                            return settingAcc;
                        }, {})
                    };
                    return acc;
                }, {});

                settings.value = transformedData;
                localStorage.setItem('settings', JSON.stringify(transformedData));
            } catch (error) {
                console.error('Error fetching settings:', error);
            }
        };

        const fetchBackgroundImage = async () => {
            try {
                const response = await axios.get('/background');
                backgroundImage.value = getBackgroundImage(response.data);
                localStorage.setItem('userBackground', backgroundImage.value);
            } catch (error) {
                console.error('Error fetching background:', error);
            }
        };

        const getBackgroundImage = (theme) => {
            const formattedTheme = theme.toLowerCase().replace(/ /g, '_');
            return `images/backgrounds/${formattedTheme}.webp`;
        };

        const loadSettingsFromStorage = () => {
            const storedSettings = localStorage.getItem('settings');
            if (storedSettings) {
                try {
                    settings.value = JSON.parse(storedSettings);
                } catch (error) {
                    console.error('Error parsing stored settings:', error);
                    settings.value = {};
                }
            }
        };

        const toggleStatsModal = () => {
            showStatsModal.value = !showStatsModal.value;
        };

        const toggleSettingsModal = () => {
            showSettingsModal.value = !showSettingsModal.value;
        };

        const handleSettingsSaved = async () => {
            await Promise.all([fetchSettings(), fetchBackgroundImage()]);
        };

        watch(backgroundImage, (newValue) => {
            localStorage.setItem('userBackground', newValue);
            const newUrl = newValue ? `url(${newValue})` : '';
            if (activeLayer.value === 'a') {
                bgB.value = newUrl;
                activeLayer.value = 'b';
            } else {
                bgA.value = newUrl;
                activeLayer.value = 'a';
            }
        });

        const handleKeydown = (e) => {
            if (e.key === 'Escape' && zenMode.value && !showStatsModal.value && !showSettingsModal.value) {
                zenMode.value = false;
            }
        };

        loadSettingsFromStorage();

        onMounted(() => startDiscordActivity(props.discordClientId, { isAuthenticated: props.isAuthenticated }));
        onMounted(fetchSettings);
        onMounted(fetchBackgroundImage);
        onMounted(() => document.addEventListener('keydown', handleKeydown));
        onUnmounted(() => document.removeEventListener('keydown', handleKeydown));

        return {
            showStatsModal,
            showSettingsModal,
            toggleStatsModal,
            toggleSettingsModal,
            handleSettingsSaved,
            bgA,
            bgB,
            activeLayer,
            settings,
            isAuthenticated,
            zenMode,
            toggleZen,
            showProjectsPanel,
            isDiscordActivity,
            discordLayoutMode,
            isSmallLayout,
        };
    },
};
</script>

<style>
.app {
    position: relative;
}

.bg-layer {
    position: fixed;
    inset: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    opacity: 0;
    transition: opacity 0.8s ease-in-out;
    z-index: 0;
}

.bg-layer-active {
    opacity: 1;
}

.background-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1;
    transition: background 0.8s ease;
}

.overlay-zen {
    background: rgba(0, 0, 0, 0.15);
}

.zen-fade {
    transition: opacity 0.4s ease, visibility 0.4s ease;
}

.zen-hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
}

/*
 * Picture-in-picture and grid tiles in a Discord call: show only the timer
 * and its controls. The height query is a fallback for clients that don't
 * report the layout mode.
 */
.app.activity.minimal .hide-when-minimal {
    display: none;
}

.app.activity.minimal .timer-fluid {
    font-size: clamp(2.5rem, min(30vw, 42vh), 8rem);
}

@media (max-height: 300px) {
    .app.activity .hide-when-minimal {
        display: none;
    }

    .app.activity .timer-fluid {
        font-size: clamp(2.5rem, min(30vw, 42vh), 8rem);
    }
}

.zen-toggle {
    bottom: calc(1.5rem + var(--saib));
    right: calc(1.5rem + var(--sair));
}

.main-content {
    position: relative;
    z-index: 2;
    min-height: 0;
}

.header, .footer {
    position: relative;
    z-index: 2;
}
</style>
