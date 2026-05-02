function readFirst(paths) {
    const ts = Date.now() - (5 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const value = String(item.value[0]).trim();
            if (value !== "") return value;
        }
    }
    return "";
}

const url = readFirst([
    "InternetGatewayDevice.ManagementServer.ConnectionRequestURL",
    "Device.ManagementServer.ConnectionRequestURL",
]);

if (!url) {
    return { writable: false, value: [false, "xsd:boolean"] };
}

const lower = url.toLowerCase();
const invalid = lower.includes("0.0.0.0") || lower.includes("::") || lower === "n/a";
const reachable = /^https?:\/\//.test(lower) && !invalid;

return { writable: false, value: [reachable, "xsd:boolean"] };
