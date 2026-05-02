const DEFAULT_VALUE = -1;

function parseIntSafe(value) {
    if (value === null || value === undefined) return null;
    const parsed = parseInt(String(value).trim(), 10);
    return Number.isNaN(parsed) ? null : parsed;
}

function readFirstInt(paths) {
    const ts = Date.now() - (10 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const parsed = parseIntSafe(item.value[0]);
            if (parsed !== null) return parsed;
        }
    }
    return null;
}

function readFirstString(paths) {
    const ts = Date.now() - (10 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const value = String(item.value[0]).trim();
            if (value !== "") return value;
        }
    }
    return null;
}

const liveCounter = readFirstInt([
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Stats.ConnectionDrops",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.Stats.ConnectionDrops",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Stats.ConnectionDrops",
    "Device.PPP.Interface.1.Stats.ConnectionDrops",
    "Device.PPP.Interface.2.Stats.ConnectionDrops",
]);

if (liveCounter !== null) {
    return { writable: true, value: [Math.max(0, liveCounter), "xsd:int"] };
}

// Fallback heuristic: if PPP reports non-idle error, expose minimum 1 drop signal.
const lastError = readFirstString([
    "VirtualParameters.PPPLastError",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.LastConnectionError",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.LastConnectionError",
    "Device.PPP.Interface.1.LastConnectionError",
]);

if (lastError) {
    const normalized = lastError.toUpperCase();
    const okValues = ["ERROR_NONE", "NO_ERROR", "NONE", "IDLE_DISCONNECT", "N/A", "N A"];
    const hasIssue = okValues.every((candidate) => normalized !== candidate);
    if (hasIssue) {
        return { writable: true, value: [1, "xsd:int"] };
    }
    return { writable: true, value: [0, "xsd:int"] };
}

if ("value" in args[1] && args[1].value && args[1].value[0] !== null && args[1].value[0] !== undefined) {
    const persisted = parseIntSafe(args[1].value[0]);
    if (persisted !== null) {
        return { writable: true, value: [Math.max(-1, persisted), "xsd:int"] };
    }
}

return { writable: true, value: [DEFAULT_VALUE, "xsd:int"] };
