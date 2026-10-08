<template>
    <header
        class="flex justify-between items-center"
        :class="isDiscordActivity ? 'gap-3 px-4 sm:px-6 py-3' : 'px-6 md:px-24 py-6 mb-16 short:py-2 short:mb-2'"
    >
        <div class="flex items-center space-x-2">
            <img
                src="/images/logo.webp"
                alt="Pomopensource Logo"
                :class="isDiscordActivity ? 'h-7 sm:h-9' : 'h-16 md:h-20 short:h-10'" />
        </div>
        <nav class="flex" :class="isDiscordActivity ? 'gap-2' : 'space-x-3'" aria-label="Main navigation">
            <!-- The Activity has no room for the projects list under the timer. -->
            <button
                v-if="isDiscordActivity"
                @click="$emit('toggleProjects')"
                aria-label="Open projects"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-folder w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">projects</span>
            </button>
            <button
                @click="$emit('toggleStats')"
                aria-label="Open statistics"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">stats</span>
            </button>
            <button
                @click="$emit('toggleSettings')"
                aria-label="Open settings"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-cog w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">settings</span>
            </button>
            <!-- Up here rather than in a corner, where Discord's call controls
                 cover it. -->
            <button
                v-if="isDiscordActivity"
                @click="$emit('toggleZen')"
                aria-label="Enter zen mode"
                title="Zen mode: hide everything but the timer"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-expand w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">zen</span>
            </button>
            <!-- Inside a Discord Activity, Discord handles sign-in. -->
            <a
                v-if="!auth && !isDiscordActivity"
                href="/login"
                aria-label="Sign in or create account"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-user w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">login</span>
            </a>
            <a
                v-if="auth && !isDiscordActivity"
                @click.prevent="logout"
                aria-label="Sign out"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition cursor-pointer"
            >
                <i class="fas fa-sign-out-alt w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">logout</span>
            </a>
        </nav>
    </header>
</template>

<script>
import axios from 'axios';
import { isDiscordActivity } from '../discord.js';

export default {
    name: 'Header',
    data: () => ({ isDiscordActivity }),
    emits: ['toggleStats', 'toggleSettings', 'toggleProjects', 'toggleZen'],
    props: {
        auth: { default: false },
    },
    methods: {
        async logout() {
            try {
                await axios.post('/logout');
                window.location.reload();
            } catch (error) {
                console.error('Logout error:', error);
            }
        }
    }
};
</script>
