<template>
    <header
        class="flex justify-between items-center px-6 md:px-24"
        :class="isDiscordActivity ? 'py-3 mb-2' : 'py-6 mb-16 short:py-2 short:mb-2'"
    >
        <div class="flex items-center space-x-2">
            <img
                src="/images/logo.webp"
                alt="Pomopensource Logo"
                :class="isDiscordActivity ? 'h-10' : 'h-16 md:h-20 short:h-10'" />
        </div>
        <nav class="flex items-center space-x-3" aria-label="Main navigation">
            <DiscordAvatar v-if="discordSession.user" :user="discordSession.user" :size="32" />
            <button
                v-if="notSynced"
                @click="explainNotSynced"
                class="flex items-center gap-1.5 py-1 px-2.5 rounded-full bg-amber-500/25 text-amber-100 text-xs font-inter"
                aria-label="Not synced: why?"
            >
                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                <span>not synced</span>
            </button>
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
import { isDiscordActivity, discordSession } from '../discord.js';
import DiscordAvatar from './DiscordAvatar.vue';
import { useToast } from '../composables/toast.js';

export default {
    name: 'Header',
    components: { DiscordAvatar },
    data: () => ({ isDiscordActivity, discordSession }),
    emits: ['toggleStats', 'toggleSettings', 'toggleProjects'],
    props: {
        auth: { default: false },
        // Inside Discord, when sign-in failed and nothing reaches the account.
        notSynced: { type: Boolean, default: false },
    },
    methods: {
        explainNotSynced() {
            useToast().error('Discord sign-in did not work: your sessions and projects are only saved on this device. Reopen the Activity to try again.');
        },
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
