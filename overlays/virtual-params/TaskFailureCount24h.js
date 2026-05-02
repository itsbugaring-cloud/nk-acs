const DEFAULT_VALUE = -1;

function parseIntSafe(value) {
    if (value === null || value === undefined) return null;
    const parsed = parseInt(String(value).trim(), 10);
    return Number.isNaN(parsed) ? null : parsed;
}

// Keep writable so external automation can persist a real 24h failure counter.
if ("value" in args[1] && args[1].value && args[1].value[0] !== null && args[1].value[0] !== undefined) {
    const persisted = parseIntSafe(args[1].value[0]);
    if (persisted !== null) {
        return { writable: true, value: [Math.max(-1, persisted), "xsd:int"] };
    }
}

return { writable: true, value: [DEFAULT_VALUE, "xsd:int"] };
