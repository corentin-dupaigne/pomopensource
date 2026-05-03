<template>
    <header class="flex justify-between items-center py-6 px-6 md:px-24 mb-16">
        <div class="flex items-center space-x-2">
            <img
                src="/images/logo.webp"
                alt="Pomopensource Logo"
                class="h-16 md:h-20" />
        </div>
        <nav class="flex space-x-3" aria-label="Main navigation">
            <button
                @click="$emit('toggleStats')"
                aria-label="Open statistics"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fa-solid fa-chart-simple" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">Stats</span>
            </button>
            <button
                @click="$emit('toggleSettings')"
                aria-label="Open settings"
                class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-cog w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">Settings</span>
            </button>
            <a
                v-if="!auth"
                href="register"
                aria-label="Sign in or create account"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition"
            >
                <i class="fas fa-user w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">Login</span>
            </a>
            <a
                v-if="auth"
                @click.prevent="logout"
                aria-label="Sign out"
                class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 transition cursor-pointer"
            >
                <i class="fas fa-sign-out-alt w-4 h-4" aria-hidden="true"></i>
                <span class="hidden md:inline-block text-sm font-inter">Logout</span>
            </a>
        </nav>
    </header>
</template>

<script>
import axios from 'axios';

export default {
    name: 'Header',
    emits: ['toggleStats', 'toggleSettings'],
    props: {
        auth: {
            default: false
        }
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
