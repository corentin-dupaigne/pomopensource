import { reactive } from 'vue';

/**
 * The timer shared by everyone in a Discord call, for the settings modal to
 * show and change its durations. Timer.vue fills it while it follows one.
 */
export const callTimer = reactive({
    active: false,
    // Minutes per timer type: { pomodoro, short_break, long_break }.
    durations: null,
    setDurations: null,
});
