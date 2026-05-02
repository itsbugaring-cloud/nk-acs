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

function firstDns(value) {
    if (!value) return "N/A";
    const parts = value.split(/[,\s]+/).map((v) => v.trim()).filter(Boolean);
    return parts.length ? parts[0] : "N/A";
}

const raw = readFirst([
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DNSServers",
    "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.DNSServers",
    "Device.IP.Interface.1.DNSServer",
    "Device.DNS.Client.Server.1.DNSServer",
    "Device.DNS.Client.Server.2.DNSServer",
]);

return { writable: false, value: [firstDns(raw), "xsd:string"] };
