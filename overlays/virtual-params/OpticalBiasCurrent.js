function parseNumeric(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") return value;
    const match = String(value).match(/-?\d+(?:\.\d+)?/);
    return match ? parseFloat(match[0]) : null;
}

function normalizeBiasCurrent(numeric) {
    // Common representations: uA / 1000, or direct mA.
    if (numeric >= 1000) return (numeric / 1000).toFixed(3);
    return numeric.toFixed(3);
}

function readBiasCurrent(paths) {
    const ts = Date.now() - (120 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            const numeric = item && item.value ? parseNumeric(item.value[0]) : null;
            if (numeric === null || Number.isNaN(numeric)) continue;
            return normalizeBiasCurrent(numeric);
        }
    }
    return "N/A";
}

const paths = [
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_FH_GponInterfaceConfig.BiasCurrent",
    "InternetGatewayDevice.X_ALU_OntOpticalParam.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_Optical.BiasCurrent",
    "InternetGatewayDevice.X_Tenda_PON.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_Tenda_Optical.BiasCurrent",
    "InternetGatewayDevice.WANDevice.1.X_C-Data_PON_Interface.BiasCurrent",
    "Device.Optical.Interface.1.BiasCurrent",
];

const result = readBiasCurrent(paths);
return { writable: false, value: [result, "xsd:string"] };
