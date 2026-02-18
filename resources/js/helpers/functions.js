export function toYmd (d)
{
    if (!d) return null;

    if (typeof d === "string" && /^\d{4}-\d{2}-\d{2}$/.test(d)) return d;

    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return null;

    return dt.toISOString().slice(0, 10);
}