function parseNumber(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") return Number.isFinite(value) ? value : null;

    const text = String(value).trim();
    if (text === "") return null;
    const parsed = Number(text);
    return Number.isFinite(parsed) ? parsed : null;
}

function readFirstNumber(paths) {
    const ts = Date.now() - (10 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const parsed = parseNumber(item.value[0]);
            if (parsed !== null) return parsed;
        }
    }
    return null;
}

const crcCounter = readFirstNumber([
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.CRCErrors",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.CRCError",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.HECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.Stats.CRCError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.Stats.HECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.Stats.CRCError",
    "Device.Optical.Interface.1.Stats.CRCError",
    "Device.Optical.Interface.1.Stats.CRCErrors",
]);

if (crcCounter === null) {
    return { writable: false, value: ["N/A", "xsd:string"] };
}

const uptimeSec = readFirstNumber([
    "InternetGatewayDevice.DeviceInfo.UpTime",
    "Device.DeviceInfo.UpTime",
    "VirtualParameters.DeviceUptime",
]);

if (uptimeSec !== null && uptimeSec > 0) {
    const ratePerHour = crcCounter / (uptimeSec / 3600);
    return { writable: false, value: [ratePerHour.toFixed(6), "xsd:string"] };
}

return { writable: false, value: [String(Math.max(0, crcCounter)), "xsd:string"] };
