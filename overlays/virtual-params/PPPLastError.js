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
    return "N/A";
}

const result = readFirst([
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.LastConnectionError",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.LastConnectionError",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.3.LastConnectionError",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.4.LastConnectionError",
    "Device.PPP.Interface.1.LastConnectionError",
    "Device.PPP.Interface.2.LastConnectionError",
    "Device.PPP.Interface.3.LastConnectionError",
    "Device.PPP.Interface.4.LastConnectionError",
]);

return { writable: false, value: [result, "xsd:string"] };
