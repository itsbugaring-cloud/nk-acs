function readFirst(paths) {
    const ts = Date.now() - (60 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const value = String(item.value[0]).trim();
            if (value !== "") return value;
        }
    }
    return "N/A";
}

const raw = readFirst([
    "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
    "Device.DeviceInfo.SoftwareVersion",
]);

const normalized = raw === "N/A" ? raw : raw.toUpperCase().replace(/\s+/g, "");
return { writable: false, value: [normalized, "xsd:string"] };
