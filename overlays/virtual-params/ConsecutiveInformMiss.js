function readInt(paths) {
    const ts = Date.now() - (5 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const value = parseInt(item.value[0], 10);
            if (!Number.isNaN(value)) return value;
        }
    }
    return null;
}

const ageSec = readInt([
    "VirtualParameters.LastInformAgeSec",
]);

const intervalSec = readInt([
    "VirtualParameters.PeriodicInformIntervalActual",
    "InternetGatewayDevice.ManagementServer.PeriodicInformInterval",
    "Device.ManagementServer.PeriodicInformInterval",
]);

if (ageSec === null || ageSec < 0 || intervalSec === null || intervalSec <= 0) {
    return { writable: false, value: [-1, "xsd:int"] };
}

if (ageSec <= intervalSec) {
    return { writable: false, value: [0, "xsd:int"] };
}

const missed = Math.max(0, Math.floor(ageSec / intervalSec) - 1);
return { writable: false, value: [missed, "xsd:int"] };
