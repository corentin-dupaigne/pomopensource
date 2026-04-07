<template>
    <div class="app min-h-screen bg-cover bg-center bg-no-repeat flex flex-col"
         :style="{ backgroundImage: `url(${backgroundImage})` }">
        <div class="header">
            <Header @toggleStats="toggleStatsModal" @toggle-settings="toggleSettingsModal" :auth="isAuthenticated" />
        </div>
        <main class="flex flex-col items-center justify-center text-white main-content">
            <ProjectsAndTasks :projects="projects" :settings="settings" :isAuthenticated="isAuthenticated"/>
        </main>
        <StatsModal v-if="showStatsModal" @close="toggleStatsModal" />
        <SettingsModal v-if="showSettingsModal" @close="toggleSettingsModal" />
        <div class="background-overlay"></div>
    </div>
</template>

<script>
import {ref, onMounted, watch, computed} from 'vue';
import Header from '../components/Header.vue';
import ProjectsAndTasks from '../components/ProjectsAndTasks.vue';
import Footer from '../components/Footer.vue';
import StatsModal from '../components/StatsModal.vue';
import SettingsModal from '../components/SettingsModal.vue';
import axios from 'axios';

export default {
    components: {
        Header,
        ProjectsAndTasks,
        Footer,
        StatsModal,
        SettingsModal
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

        const fetchSettings = async () => {
            try {
                const response = await axios.get('/user-settings');
                const rawData = response.data;

                // Transform the data into a more usable JSON format
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

        // Fetch settings on component mount
        const fetchBackgroundImage = async () => {
            try {
                const response = await axios.get('/background');
                backgroundImage.value = getBackgroundImage(response.data);
                localStorage.setItem('userBackground', backgroundImage.value);
            } catch (error) {
                console.error('Error fetching settings:', error);
            }
        };

        const checkAuthentication = async () => {
            try {
                const response = await axios.get('/isAuthenticated');
                isAuthenticated.value = response.data;
            } catch (error) {
                console.error('Error fetching auth status:', error);
            }
        };

        // Construct the image path based on the theme name
        const getBackgroundImage = (theme) => {
            const formattedTheme = theme.toLowerCase().replace(/ /g, '_');
            return `images/backgrounds/${formattedTheme}.webp`;
        };

        // Save settings and update background image
        const saveSettings = async (newSettingValue) => {
            try {
                await axios.post('/user-settings', {settings: [{id: 'theme_id', value: newSettingValue}]});
                backgroundImage.value = getBackgroundImage(newSettingValue);
            } catch (error) {
                console.error('Error saving settings:', error);
            }
        };

        const loadSettingsFromStorage = () => {
            const storedSettings = localStorage.getItem('settings');
            if (storedSettings) {
                try {
                    settings.value = JSON.parse(storedSettings);
                } catch (error) {
                    console.error('Error parsing stored settings:', error);
                    settings.value = {};  // Fallback to empty object if parsing fails
                }
            }
        };

        const toggleStatsModal = () => {
            showStatsModal.value = !showStatsModal.value;
        };

        const toggleSettingsModal = () => {
            showSettingsModal.value = !showSettingsModal.value;
        };

        // Watch for background changes and save settings
        watch(backgroundImage, (newValue) => {
            localStorage.setItem('userBackground', newValue);
        });

        loadSettingsFromStorage();

        // Fetch settings on mount
        onMounted(fetchSettings);
        onMounted(fetchBackgroundImage);
        checkAuthentication();

        return {
            showStatsModal,
            showSettingsModal,
            toggleStatsModal,
            toggleSettingsModal,
            backgroundImage,
            settings,
            saveSettings,
            isAuthenticated
        };
    },
};
</script>

<style>
.app {
    position: relative;
    background-attachment: fixed;
}

.background-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1;
}

.bg-cover {
    background-size: cover;
}

.bg-no-repeat {
    background-repeat: no-repeat;
}

.bg-center {
    background-position: center;
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
