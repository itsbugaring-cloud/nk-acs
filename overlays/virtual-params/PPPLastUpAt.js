function parseNumber(value) {
    if (value === null || value === undefined) return null;
    const parsed = Number(String(value).trim());
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

const pppUptimeSec = readFirstNumber([
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.Uptime",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.Uptime",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.2.WANPPPConnection.1.Uptime",
    "Device.PPP.Interface.1.Uptime",
    "Device.PPP.Interface.2.Uptime",
    "Device.PPP.Interface.3.Uptime",
]);

if (pppUptimeSec === null || pppUptimeSec < 0) {
    return { writable: false, value: ["N/A", "xsd:string"] };
}

const lastUpAt = new Date(Date.now() - (pppUptimeSec * 1000)).toISOString();
return { writable: false, value: [lastUpAt, "xsd:string"] };
