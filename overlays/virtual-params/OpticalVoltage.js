function parseNumeric(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") return value;
    const match = String(value).match(/-?\d+(?:\.\d+)?/);
    return match ? parseFloat(match[0]) : null;
}

function normalizeVoltage(numeric) {
    // Common cases: 3300 (mV), 3.3 (V), 33000 (deci-mV style).
    if (numeric >= 10000) return (numeric / 10000).toFixed(3);
    if (numeric >= 1000) return (numeric / 1000).toFixed(3);
    return numeric.toFixed(3);
}

function readVoltage(paths) {
    const ts = Date.now() - (120 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            const numeric = item && item.value ? parseNumeric(item.value[0]) : null;
            if (numeric === null || Number.isNaN(numeric)) continue;
            return normalizeVoltage(numeric);
        }
    }
    return "N/A";
}

const paths = [
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.SupplyVoltage",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.SupplyVoltage",
    "InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.SupplyVoltage",
    "InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.SupplyVoltage",
    "InternetGatewayDevice.WANDevice.1.X_FH_GponInterfaceConfig.SupplyVoltage",
    "InternetGatewayDevice.X_ALU_OntOpticalParam.Voltage",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.SupplyVoltage",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.Voltage",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_Optical.Voltage",
    "InternetGatewayDevice.X_Tenda_PON.Voltage",
    "InternetGatewayDevice.WANDevice.1.X_Tenda_Optical.Voltage",
    "InternetGatewayDevice.WANDevice.1.X_C-Data_PON_Interface.Voltage",
    "Device.Optical.Interface.1.Voltage",
];

const result = readVoltage(paths);
return { writable: false, value: [result, "xsd:string"] };
