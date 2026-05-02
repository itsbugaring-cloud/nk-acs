function readFirstValue(path) {
    const ts = Date.now() - (5 * 60 * 1000);
    const declared = declare(path, { path: ts, value: ts });
    for (const item of declared) {
        if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
        return item.value[0];
    }
    return null;
}

const ageRaw = readFirstValue("VirtualParameters.LastInformAgeSec");
const intervalRaw = readFirstValue("VirtualParameters.PeriodicInformIntervalActual");

const age = parseInt(ageRaw, 10);
const interval = parseInt(intervalRaw, 10);

if (Number.isNaN(age) || age < 0) {
    return { writable: false, value: ["unknown", "xsd:string"] };
}

const effectiveInterval = !Number.isNaN(interval) && interval > 0 ? interval : 120;
const onlineThreshold = Math.max(900, effectiveInterval * 3);
const state = age <= onlineThreshold ? "online" : "offline";

return { writable: false, value: [state, "xsd:string"] };
