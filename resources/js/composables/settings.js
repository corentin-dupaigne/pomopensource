import { ref } from 'vue';

// Checkbox settings come back as 'true'/'false' (seeded defaults), '1'/'0'
// (saved to the database) or 1/0 (saved to a guest's session).
export const isEnabled = (value) =>
    value === true || value === 1 || value === '1' || value === 'true';

// Bumped each time the user saves a setting and the new values have loaded,
// as opposed to settings changing because they were first loaded.
export const settingsSaved = ref(0);
