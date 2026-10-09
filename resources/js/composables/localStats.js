const LOCAL_SESSIONS_KEY = 'localSessions';
const LOCAL_PROJECTS_KEY = 'localProjects';
const PROJECT_PREFIX = 'project:rbNiqBehszLPVzMmR_';
const TASK_PREFIX = 'task:rbNiqBehszLPVzMmR_';

// Dates are stored as local 'YYYY-MM-DD' strings. toISOString() and
// new Date('YYYY-MM-DD') both use UTC, which shifts days near midnight.
export function toLocalDateString(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

export function parseLocalDate(str) {
    const [y, m = 1, d = 1] = String(str).split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function getLocalSessions() {
    try { return JSON.parse(localStorage.getItem(LOCAL_SESSIONS_KEY) || '[]'); }
    catch { return []; }
}

export function addLocalSession(session) {
    const sessions = getLocalSessions();
    sessions.push(session);
    localStorage.setItem(LOCAL_SESSIONS_KEY, JSON.stringify(sessions));
}

/**
 * Projects used to have tasks. Like the server's migration, a session spent
 * on a task moves to the task's project, with the task's name as its note,
 * and the tasks are dropped. Does nothing once done.
 */
export function foldLocalTasks() {
    let projects;
    try { projects = JSON.parse(localStorage.getItem(LOCAL_PROJECTS_KEY) || '[]'); }
    catch { return; }
    if (!projects.some((p) => 'tasks' in p || 'showTasks' in p)) return;

    const taskOwners = {};
    for (const project of projects) {
        for (const task of project.tasks ?? []) {
            taskOwners[String(task.id)] = { projectId: project.id, name: task.name };
        }
    }

    const sessions = getLocalSessions().map((session) => {
        if (!session.selectedId?.startsWith(TASK_PREFIX)) return session;
        const owner = taskOwners[session.selectedId.slice(TASK_PREFIX.length)];
        return {
            ...session,
            selectedId: owner ? PROJECT_PREFIX + owner.projectId : '',
            note: session.note ?? owner?.name,
        };
    });

    localStorage.setItem(LOCAL_SESSIONS_KEY, JSON.stringify(sessions));
    localStorage.setItem(LOCAL_PROJECTS_KEY, JSON.stringify(projects.map(({ id, name }) => ({ id, name }))));
}

export function computeStats(sessions) {
    const totalSeconds = sessions.reduce((sum, s) => sum + (s.duration_seconds || 0), 0);
    const uniqueDates = new Set(sessions.map(s => s.date));

    let streak = 0;
    const today = new Date();
    for (let i = 0; i < 365; i++) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const dateStr = toLocalDateString(d);
        if (uniqueDates.has(dateStr)) {
            streak++;
        } else if (i > 0) {
            break;
        }
    }

    return {
        hours_focused: Math.round(totalSeconds / 3600 * 10) / 10,
        days_accessed: uniqueDates.size,
        day_streak: streak,
    };
}

export function computeCalendarData(sessions) {
    const map = {};
    for (const s of sessions) {
        if (!s.date) continue;
        map[s.date] = (map[s.date] || 0) + (s.duration_seconds || 0);
    }
    return Object.entries(map).map(([date, seconds]) => ({
        date,
        has_session: true,
        minutes_focused: Math.round(seconds / 60),
    }));
}

export function computeProjectStats(sessions, localProjects) {
    const projectMap = {};

    for (const project of localProjects) {
        projectMap[String(project.id)] = {
            id: project.id,
            name: project.name,
            total_time_focused: 0,
        };
    }

    for (const { selectedId, duration_seconds = 0 } of sessions) {
        if (!selectedId?.startsWith(PROJECT_PREFIX)) continue;
        const project = projectMap[selectedId.slice(PROJECT_PREFIX.length)];
        if (project) project.total_time_focused += duration_seconds;
    }

    return Object.values(projectMap).filter((p) => p.total_time_focused > 0);
}
