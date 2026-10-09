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
