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

const fecCounter = readFirstNumber([
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.FECError",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.FECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.Stats.FECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.FECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.Stats.FECError",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.FECError",
    "Device.Optical.Interface.1.Stats.FECError",
    "Device.Optical.Interface.1.Stats.FECErrors",
]);

if (fecCounter === null) {
    return { writable: false, value: ["N/A", "xsd:string"] };
}

const uptimeSec = readFirstNumber([
    "InternetGatewayDevice.DeviceInfo.UpTime",
    "Device.DeviceInfo.UpTime",
    "VirtualParameters.DeviceUptime",
]);

if (uptimeSec !== null && uptimeSec > 0) {
    const ratePerHour = fecCounter / (uptimeSec / 3600);
    return { writable: false, value: [ratePerHour.toFixed(6), "xsd:string"] };
}

return { writable: false, value: [String(Math.max(0, fecCounter)), "xsd:string"] };
