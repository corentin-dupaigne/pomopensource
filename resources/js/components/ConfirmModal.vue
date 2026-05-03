<template>
    <teleport to="body">
        <div
            v-if="visible"
            class="fixed inset-0 bg-black/70 flex items-center justify-center z-[60]"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="labelId"
            @keydown.esc="$emit('cancel')"
            @click.self="$emit('cancel')"
        >
            <div
                ref="dialogRef"
                tabindex="-1"
                class="bg-white/15 backdrop-blur-lg rounded-lg shadow-xl p-6 max-w-sm w-full mx-4 text-white focus:outline-none"
            >
                <h3 :id="labelId" class="text-lg font-bold font-oswald mb-2">{{ title }}</h3>
                <p class="text-white/70 text-sm mb-6">{{ message }}</p>
                <div class="flex justify-end gap-3">
                    <button
                        @click="$emit('cancel')"
                        class="px-4 py-2 bg-white/10 text-white rounded-lg hover:bg-white/20 transition text-sm font-inter font-semibold"
                    >
                        Cancel
                    </button>
                    <button
                        @click="$emit('confirm')"
                        class="px-4 py-2 bg-red-600/70 text-white rounded-lg hover:bg-red-700/80 transition text-sm font-inter font-semibold"
                    >
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
import { ref, watch, nextTick } from 'vue';

export default {
    props: {
        visible: Boolean,
        title: { type: String, default: 'Confirm action' },
        message: { type: String, default: 'Are you sure?' },
        confirmLabel: { type: String, default: 'Delete' },
    },
    emits: ['confirm', 'cancel'],
    setup(props) {
        const dialogRef = ref(null);
        const labelId = `confirm-title-${Math.random().toString(36).slice(2)}`;

        watch(() => props.visible, async (val) => {
            if (val) {
                await nextTick();
                dialogRef.value?.focus();
            }
        });

        return { dialogRef, labelId };
    }
};
</script>
