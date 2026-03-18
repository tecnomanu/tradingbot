import { useState, useEffect } from "react";
import { timeSince } from "@/utils/timeago";

export function useTimeSince(dateStr: string | null | undefined, intervalMs = 60_000): string {
    const [text, setText] = useState(() => timeSince(dateStr));

    useEffect(() => {
        setText(timeSince(dateStr));
        const timer = setInterval(() => setText(timeSince(dateStr)), intervalMs);
        return () => clearInterval(timer);
    }, [dateStr, intervalMs]);

    return text;
}
