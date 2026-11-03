// What a pomodoro is on: one text, the subject. The same text, whatever its
// case or spacing, is the same subject, as the server matches them.
export const cleanSubject = (name) => String(name ?? '').replace(/\s+/g, ' ').trim();

export const subjectKey = (name) => cleanSubject(name).toLowerCase();
