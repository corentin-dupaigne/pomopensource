<template>
    <div class="app min-h-screen flex flex-col">
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'a' }" :style="{ backgroundImage: bgA }"></div>
        <div class="bg-layer" :class="{ 'bg-layer-active': activeLayer === 'b' }" :style="{ backgroundImage: bgB }"></div>
        <div class="background-overlay"></div>
        <div class="header">
            <Header @toggleStats="toggleStatsModal" @toggle-settings="toggleSettingsModal" :auth="isAuthenticated" />
        </div>
        <main class="flex flex-col items-center justify-center text-white main-content">
            <ProjectsAndTasks :projects="projects" :settings="settings" :isAuthenticated="isAuthenticated"/>
        </main>
        <StatsModal v-if="showStatsModal" @close="toggleStatsModal" />
        <SettingsModal v-if="showSettingsModal" @close="toggleSettingsModal" @saved="handleSettingsSaved" />
        <Toast />
    </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import Header from '../components/Header.vue';
import ProjectsAndTasks from '../components/ProjectsAndTasks.vue';
import Footer from '../components/Footer.vue';
import StatsModal from '../components/StatsModal.vue';
import SettingsModal from '../components/SettingsModal.vue';
import Toast from '../components/Toast.vue';
import axios from 'axios';

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
    },
    setup() {
        const showStatsModal = ref(false);
        const showSettingsModal = ref(false);
        const backgroundImage = ref(localStorage.getItem('userBackground') || '');
        const isAuthenticated = ref(false);
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

        const checkAuthentication = async () => {
            try {
                const response = await axios.get('/isAuthenticated');
                isAuthenticated.value = !!response.data;
            } catch (error) {
                console.error('Error fetching auth status:', error);
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

        loadSettingsFromStorage();

        onMounted(fetchSettings);
        onMounted(fetchBackgroundImage);
        checkAuthentication();

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
            isAuthenticated
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
}

.main-content {
    position: relative;
    z-index: 2;
}

.header, .footer {
    position: relative;
    z-index: 2;
}
</style>
