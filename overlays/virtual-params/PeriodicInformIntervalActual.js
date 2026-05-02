function readInterval(paths) {
    const ts = Date.now() - (60 * 60 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
            const numeric = parseInt(item.value[0], 10);
            if (!Number.isNaN(numeric) && numeric > 0) return numeric;
        }
    }
    return 120;
}

const interval = readInterval([
    "InternetGatewayDevice.ManagementServer.PeriodicInformInterval",
    "Device.ManagementServer.PeriodicInformInterval",
]);

return { writable: false, value: [interval, "xsd:int"] };
