import { ref, shallowRef } from 'vue';

// Checkbox settings come back as 'true'/'false' (seeded defaults), '1'/'0'
// (saved to the database) or 1/0 (saved to a guest's session).
export const isEnabled = (value) =>
    value === true || value === 1 || value === '1' || value === 'true';

// Bumped each time the user saves a setting and the new values have loaded,
// as opposed to settings changing because they were first loaded.
export const settingsSaved = ref(0);

// The settings as /user-settings sends them, kept from the page's own load so
// the settings modal opens with them instead of growing once they arrive.
export const settingsCategories = shallowRef(null);

// "Singing Bowl" is public/sounds/singing_bowl.mp3, as the seeder names it.
export const alertSoundUrl = (sound) =>
    `/sounds/${(sound || 'Waves').toLowerCase().replace(/ /g, '_')}.mp3`;

// The alert volume setting, 0 to 100, as an audio element's 0 to 1.
export const audioVolume = (value) => Math.min(Math.max(parseInt(value) / 100, 0), 1);
