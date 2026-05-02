function readFirstValue(paths) {
    const ts = Date.now() - (5 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            return item.value[0];
        }
    }
    return null;
}

function toMillis(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") {
        // If value already in milliseconds epoch.
        if (value > 1000000000000) return value;
        // Seconds epoch fallback.
        if (value > 1000000000) return value * 1000;
        return null;
    }

    const parsed = Date.parse(String(value));
    return Number.isNaN(parsed) ? null : parsed;
}

const lastInformRaw = readFirstValue(["_lastInform"]);
const lastInformMs = toMillis(lastInformRaw);
const now = Date.now();
const ageSec = lastInformMs ? Math.max(0, Math.floor((now - lastInformMs) / 1000)) : -1;

return { writable: false, value: [ageSec, "xsd:int"] };
