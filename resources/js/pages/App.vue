<template>
    <!-- Inside Discord the app fills the frame exactly: no page scroll. -->
    <div class="app safe-area flex flex-col" :class="[isDiscordActivity ? ['activity h-[100dvh] overflow-hidden', { minimal: isSmallLayout(discordLayoutMode) }] : 'min-h-screen', { quiet }]">
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'a' }" :style="{ backgroundImage: bgA }"></div>
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'b' }" :style="{ backgroundImage: bgB }"></div>
        <div class="background-overlay"></div>

        <!-- Rendered only once the Activity knows who is signed in, so guest
             data never flashes before the account's. -->
        <div v-if="!ready" class="main-content flex-1 flex flex-col items-center justify-center gap-4 text-white font-inter" role="status">
            <i class="fas fa-circle-notch fa-spin text-3xl text-white/70" aria-hidden="true"></i>
            <p class="text-sm text-white/70">Connecting to Discord…</p>
        </div>

        <template v-else>
        <div class="header quiet-fade hide-when-minimal">
            <Header @toggleStats="toggleStatsModal" @toggle-settings="toggleSettingsModal" @toggle-projects="showProjectsPanel = !showProjectsPanel" :auth="isAuthenticated" :notSynced="notSynced" />
        </div>

        <!-- In the Activity, scroll rather than cut off when the frame is too
             short; "safe" keeps the top reachable while centred. -->
        <main
            class="flex-1 flex flex-col items-center text-white main-content"
            :class="isDiscordActivity ? '[justify-content:safe_center] overflow-y-auto py-2' : 'justify-center'"
        >
            <Projects
                :projects="accountProjects"
                :settings="settings"
                :isAuthenticated="isAuthenticated"
                :panelOpen="showProjectsPanel"
                @closePanel="showProjectsPanel = false"
                @openPanel="showProjectsPanel = true"
            />
        </main>

        <!-- Real full screen, where the browser allows it. Not in the Activity,
             whose frame Discord can make full screen itself. -->
        <button
            v-if="canFullscreen"
            @click="toggleFullscreen"
            :aria-label="isFullscreen ? 'Exit full screen' : 'Full screen'"
            :title="isFullscreen ? 'Exit full screen (F)' : 'Full screen (F)'"
            class="fullscreen-toggle quiet-fade fixed z-10 flex items-center gap-1.5 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
        >
            <i :class="isFullscreen ? 'fas fa-compress' : 'fas fa-expand'" aria-hidden="true"></i>
            <span class="text-sm font-inter">{{ isFullscreen ? 'exit full screen' : 'full screen' }}</span>
        </button>

        <StatsModal v-if="showStatsModal" :isAuthenticated="isAuthenticated" @close="toggleStatsModal" />
        <SettingsModal v-if="showSettingsModal" @close="toggleSettingsModal" @saved="handleSettingsSaved" />
        </template>
        <Toast />
    </div>
</template>

<script>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import Header from '../components/Header.vue';
import Projects from '../components/Projects.vue';
import Footer from '../components/Footer.vue';
import StatsModal from '../components/StatsModal.vue';
import SettingsModal from '../components/SettingsModal.vue';
import Toast from '../components/Toast.vue';
import axios from 'axios';
import { settingsSaved, settingsCategories, isEnabled } from '../composables/settings.js';
import { useQuietWhileFocusing } from '../composables/focus.js';
import { startDiscordActivity, isDiscordActivity, discordSession, discordLayoutMode, isSmallLayout } from '../discord.js';

export default {
    components: {
        Header,
        Projects,
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

        const backgroundImage = ref(localStorage.getItem('userBackground') || '');
        // In the Activity, the Discord sign-in decides which account is used.
        const ready = computed(() => discordSession.status !== 'connecting');
        const signedInWithDiscord = computed(() => discordSession.status === 'signed-in');
        const isAuthenticated = computed(() => props.isAuthenticated || signedInWithDiscord.value);
        const accountProjects = computed(() => signedInWithDiscord.value ? discordSession.projects : props.projects);
        const notSynced = computed(() => discordSession.expired || (discordSession.status === 'guest' && !props.isAuthenticated));
        const settings = ref({});

        const canFullscreen = !isDiscordActivity && document.fullscreenEnabled;
        const isFullscreen = ref(false);
        const toggleFullscreen = () => {
            if (document.fullscreenElement) document.exitFullscreen();
            else document.documentElement.requestFullscreen().catch(() => {});
        };
        const syncFullscreen = () => { isFullscreen.value = Boolean(document.fullscreenElement); };

        // F toggles full screen, unless typing or a window is open.
        const handleKeydown = (e) => {
            if (!canFullscreen || e.key.toLowerCase() !== 'f' || e.ctrlKey || e.metaKey || e.altKey) return;
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
            if (showStatsModal.value || showSettingsModal.value || showProjectsPanel.value) return;
            e.preventDefault();
            toggleFullscreen();
        };
        onMounted(() => {
            document.addEventListener('keydown', handleKeydown);
            document.addEventListener('fullscreenchange', syncFullscreen);
        });
        onUnmounted(() => {
            document.removeEventListener('keydown', handleKeydown);
            document.removeEventListener('fullscreenchange', syncFullscreen);
        });

        // Hide everything but the time while focusing, unless turned off or a
        // window is open.
        const quiet = useQuietWhileFocusing({
            enabled: computed(() => isEnabled(settings.value?.timers?.settings?.hide_controls_while_focusing ?? true)),
            blocked: computed(() => showStatsModal.value || showSettingsModal.value || showProjectsPanel.value),
        });

        const initialBg = backgroundImage.value ? `url(${backgroundImage.value})` : '';
        const bgA = ref(initialBg);
        const bgB = ref('');
        const activeLayer = ref('a');

        const fetchSettings = async () => {
            try {
                const response = await axios.get('/user-settings');
                const rawData = response.data;
                settingsCategories.value = rawData;

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
            settingsSaved.value++;
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


        loadSettingsFromStorage();

        // Settings depend on the account, so the Activity loads them after sign-in.
        const loadAccountData = () => Promise.all([fetchSettings(), fetchBackgroundImage()]);
        onMounted(async () => {
            await startDiscordActivity(props.discordClientId);
            loadAccountData();
        });

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
            accountProjects,
            ready,
            notSynced,
            quiet,
            canFullscreen,
            isFullscreen,
            toggleFullscreen,
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

/*
 * Quiet while focusing: the controls marked quiet-fade fade out, and the
 * cursor hides, until the user moves, taps or types.
 */
.quiet-fade {
    transition: opacity 0.6s ease;
}

.app.quiet .quiet-fade {
    opacity: 0;
    pointer-events: none;
}

.app.quiet {
    cursor: none;
}

@media (prefers-reduced-motion: reduce) {
    .quiet-fade {
        transition: none;
    }
}

/*
 * Picture-in-picture and grid tiles in a Discord call: show only the timer
 * and its controls. The height query is a fallback for clients that don't
 * report the layout mode.
 */
.show-when-minimal {
    display: none;
}

.app.activity.minimal .hide-when-minimal {
    display: none;
}

.app.activity.minimal .show-when-minimal {
    display: block;
}

.app.activity.minimal .timer-fluid {
    font-size: clamp(2.5rem, min(30vw, 42vh), 8rem);
}

@media (max-height: 300px) {
    .app.activity .hide-when-minimal {
        display: none;
    }

    .app.activity .show-when-minimal {
        display: block;
    }

    .app.activity .timer-fluid {
        font-size: clamp(2.5rem, min(30vw, 42vh), 8rem);
    }
}

.fullscreen-toggle {
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
