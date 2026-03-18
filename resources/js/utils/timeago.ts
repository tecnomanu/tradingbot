export function timeSince(dateStr: string | null | undefined): string {
    if (!dateStr) return "Nunca";
    const ms = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return "Hace segundos";
    if (mins < 60) return `Hace ${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `Hace ${hrs}h ${mins % 60}m`;
    const days = Math.floor(hrs / 24);
    return `Hace ${days}d`;
}

export function timeAgo(date: Date): string {
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) return "hace unos segundos";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `hace ${minutes} min`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `hace ${hours}h`;
    const days = Math.floor(hours / 24);
    return `hace ${days}d`;
}

export function formatTimestamp(date: Date): string {
    const hoursAgo = (Date.now() - date.getTime()) / 3600000;
    const time = date.toLocaleTimeString("es", { hour: "2-digit", minute: "2-digit" });
    if (hoursAgo < 24) return time;
    const day = date.toLocaleDateString("es", { day: "2-digit", month: "2-digit" });
    return `${day} ${time}`;
}

export function timeSinceCompact(dateStr: string | null | undefined): string {
    if (!dateStr) return "Nunca";
    const ms = Date.now() - new Date(dateStr).getTime();
    const mins = Math.floor(ms / 60000);
    if (mins < 1) return "Ahora";
    if (mins < 60) return `${mins}m`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h`;
    return `${Math.floor(hrs / 24)}d`;
}
