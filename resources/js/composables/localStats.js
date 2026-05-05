const LOCAL_SESSIONS_KEY = 'localSessions';
const PROJECT_PREFIX = 'project:rbNiqBehszLPVzMmR_';
const TASK_PREFIX = 'task:rbNiqBehszLPVzMmR_';

export function getLocalSessions() {
    try { return JSON.parse(localStorage.getItem(LOCAL_SESSIONS_KEY) || '[]'); }
    catch { return []; }
}

export function addLocalSession(session) {
    const sessions = getLocalSessions();
    sessions.push(session);
    localStorage.setItem(LOCAL_SESSIONS_KEY, JSON.stringify(sessions));
}

export function computeStats(sessions) {
    const totalSeconds = sessions.reduce((sum, s) => sum + (s.duration_seconds || 0), 0);
    const uniqueDates = new Set(sessions.map(s => s.date));

    let streak = 0;
    const today = new Date();
    for (let i = 0; i < 365; i++) {
        const d = new Date(today);
        d.setDate(d.getDate() - i);
        const dateStr = d.toISOString().split('T')[0];
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
            tasks: (project.tasks || []).map(t => ({
                id: t.id,
                name: t.name,
                time_focused: 0,
            })),
        };
    }

    for (const session of sessions) {
        const { selectedId, duration_seconds = 0 } = session;
        if (!selectedId || duration_seconds === 0) continue;

        if (selectedId.startsWith(PROJECT_PREFIX)) {
            const projectId = selectedId.slice(PROJECT_PREFIX.length);
            if (projectMap[projectId]) {
                projectMap[projectId].total_time_focused += duration_seconds;
            }
        } else if (selectedId.startsWith(TASK_PREFIX)) {
            const taskId = selectedId.slice(TASK_PREFIX.length);
            for (const project of Object.values(projectMap)) {
                const task = project.tasks.find(t => String(t.id) === String(taskId));
                if (task) {
                    task.time_focused += duration_seconds;
                    project.total_time_focused += duration_seconds;
                    break;
                }
            }
        }
    }

    return Object.values(projectMap).filter(
        p => p.total_time_focused > 0 || p.tasks.some(t => t.time_focused > 0)
    );
}
