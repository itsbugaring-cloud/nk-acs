function read(path) {
    const ts = Date.now() - (60 * 60 * 1000);
    const declared = declare(path, { value: ts });
    for (const item of declared) {
        if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
        const value = String(item.value[0]).trim();
        if (value !== "") return value.toUpperCase();
    }
    return "";
}

const current = read("VirtualParameters.FirmwareVersionNormalized");
const target = read("VirtualParameters.FirmwareTarget");

let state = "UNKNOWN";
if (!target || target === "NOT_SET") {
    state = "NO_TARGET";
} else if (!current || current === "N/A") {
    state = "UNKNOWN_CURRENT";
} else if (current === target) {
    state = "UP_TO_DATE";
} else {
    state = "TARGET_MISMATCH";
}

return { writable: false, value: [state, "xsd:string"] };
