function parseNumeric(value) {
    if (value === null || value === undefined) return null;
    if (typeof value === "number") return value;
    const match = String(value).match(/-?\d+(?:\.\d+)?/);
    return match ? parseFloat(match[0]) : null;
}

function readOpticalValue(paths) {
    const ts = Date.now() - (120 * 1000);
    for (const path of paths) {
        const declared = declare(path, { path: ts, value: ts });
        for (const item of declared) {
            const numeric = item && item.value ? parseNumeric(item.value[0]) : null;
            if (numeric === null || Number.isNaN(numeric)) continue;

            // Convert common raw optical units to dBm.
            if (numeric >= 1000) return Math.round((10 * Math.log10(numeric / 10000)) * 100) / 100;
            return numeric;
        }
    }
    return "N/A";
}

const paths = [
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_FH_GponInterfaceConfig.TXPower",
    "InternetGatewayDevice.X_ALU_OntOpticalParam.TXPower",
    "InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.TXPower",
    "InternetGatewayDevice.WANDevice.1.X_VSOL_Optical.TxPower",
    "InternetGatewayDevice.X_Tenda_PON.OpticalTxPower",
    "InternetGatewayDevice.WANDevice.1.X_Tenda_Optical.TxPower",
    "InternetGatewayDevice.WANDevice.1.X_C-Data_PON_Interface.TxPower",
    "Device.Optical.Interface.1.TxPower",
];

const result = readOpticalValue(paths);
return { writable: false, value: [result, "xsd:string"] };
