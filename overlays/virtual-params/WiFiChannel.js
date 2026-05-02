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
    "Device.WiFi.Radio.1.Channel",
    "Device.WiFi.Radio.2.Channel",
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel",
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.Channel",
]);

return { writable: false, value: [result, "xsd:string"] };
