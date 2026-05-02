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

const liveValue = readFirstInt([
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.LOSCount",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.Stats.LOS",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.LOSCount",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.LOS",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.Stats.LOSCount",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.LOSCount",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.Stats.LOSCount",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.LOSCount",
    "Device.Optical.Interface.1.Stats.LOSCount",
    "Device.Optical.Interface.1.Stats.LossOfSignalCount",
    "InternetGatewayDevice.X_Tenda_PON.LOSCount",
]);

if (liveValue !== null) {
    return { writable: true, value: [Math.max(0, liveValue), "xsd:int"] };
}

if ("value" in args[1] && args[1].value && args[1].value[0] !== null && args[1].value[0] !== undefined) {
    const persisted = parseIntSafe(args[1].value[0]);
    if (persisted !== null) {
        return { writable: true, value: [Math.max(-1, persisted), "xsd:int"] };
    }
}

return { writable: true, value: [DEFAULT_VALUE, "xsd:int"] };
