<template>
    <header class="flex justify-between items-center py-6 px-6 md:px-24 mb-16">
        <div class="flex items-center space-x-2">
            <!-- Logo -->
            <img
                src="/images/logo.webp"
                alt="Pomopensource Logo"
                class="h-16 md:h-20" />
        </div>
        <div class="flex space-x-3">
            <!-- Stats Button -->
            <button @click="$emit('toggleStats')" class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20">
                <i class="fa-solid fa-chart-simple"></i>
                <span class="hidden md:inline-block text-sm font-inter">Stats</span>
            </button>
            <!-- Settings Button -->
            <button @click="$emit('toggleSettings')" class="flex items-center space-x-1 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20">
                <i class="fas fa-cog w-4 h-4"></i>
                <span class="hidden md:inline-block text-sm font-inter">Settings</span>
            </button>
            <!-- Login Button (when not authenticated) -->
            <a
              v-if="!auth"
              class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20"
              href="register"
            >
              <i class="fas fa-user w-4 h-4"></i>
              <span class="hidden md:inline-block text-sm font-inter">Login</span>
            </a>

            <!-- Logout Button (when authenticated) -->
            <a
              v-if="auth"
              @click.prevent="logout"
              class="flex items-center space-x-2 py-2 px-3 bg-white/10 text-white rounded-full hover:bg-white/20 cursor-pointer"
            >
              <i class="fas fa-sign-out-alt w-4 h-4"></i>
              <span class="hidden md:inline-block text-sm font-inter">Logout</span>
            </a>
        </div>
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

<style scoped>
/* Header specific styles */
</style>
