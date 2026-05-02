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
    "Device.IP.Interface.1.IPv6Address.1.IPAddress",
    "Device.IP.Interface.2.IPv6Address.1.IPAddress",
    "Device.IP.Interface.3.IPv6Address.1.IPAddress",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_CT-COM_IPv6IPAddress",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.X_CT-COM_IPv6IPAddress",
]);

return { writable: false, value: [result, "xsd:string"] };
