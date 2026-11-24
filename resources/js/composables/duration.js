// "1h 15m", "2h", "40m": how the stats write a time focused, in minutes.
export function formatMinutes(minutes) {
    const total = Math.round(minutes || 0);
    const h = Math.floor(total / 60);
    const m = total % 60;
    if (h === 0) return `${m}m`;
    return m === 0 ? `${h}h` : `${h}h ${m}m`;
}

// How light a day's square is, from 0 (nothing) to 4: the more time, the
// lighter, as on GitHub's contribution graph.
export function intensityOf(minutes) {
    if (!minutes) return 0;
    if (minutes < 30) return 1;
    if (minutes < 60) return 2;
    if (minutes < 120) return 3;
    return 4;
}
