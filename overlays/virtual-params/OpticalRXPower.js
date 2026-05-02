let result = '';

function parseNumeric(value) {
    if (value === null || value === undefined) {
        return null;
    }

    if (typeof value === 'number') {
        return value;
    }

    let match = String(value).match(/-?\d+(?:\.\d+)?/);
    return match ? parseFloat(match[0]) : null;
}

function getParameterValue(keys) {
    for (let key of keys) {
        let d = declare(key, {path: Date.now() - (120 * 1000), value: Date.now()});

        for (let item of d) {
            let numeric = item.value ? parseNumeric(item.value[0]) : null;
            if (numeric === null || Number.isNaN(numeric)) {
                continue;
            }

            if (numeric >= 0 ) {
                return Math.ceil(10 * Math.log10(numeric / 10000));
            } else if (numeric < 0) {
                return numeric;
            }
        }
    }

    return 'N/A';
}

if ("value" in args[1]) {
    result = args[1].value[0];
} else {
    let keys = [
        'InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.RXPower', // China Telecom
        'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.RXPower', // China Telecom GPON
        'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.RXPower', // Huawei/ZTE/Generic
        'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.RXPower', // China Unicom
        'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.RXPower', // China Mobile
      	'InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.RXPower', // ZTE
      	'InternetGatewayDevice.WANDevice.1.X_FH_GponInterfaceConfig.RXPower', // FiberHome
        'InternetGatewayDevice.X_ALU_OntOpticalParam.RXPower', // Nokia/Alcatel
        'InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.RXPower', // Generic GPON
        'InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.RXPower', // VSOL (Common)
        'InternetGatewayDevice.WANDevice.1.X_VSOL_Optical.RxPower', // VSOL (Alt)
        'Device.Optical.Interface.1.RxPower', // TR-181
        'InternetGatewayDevice.X_Tenda_PON.OpticalRxPower', // Tenda (Hypothetical/Common)
        'InternetGatewayDevice.WANDevice.1.X_Tenda_Optical.RxPower', // Tenda (Alt)
        'InternetGatewayDevice.WANDevice.1.X_C-Data_PON_Interface.RxPower' // C-Data/Zhongli
    ];

    result = getParameterValue(keys);
}

return {writable: false, value: [result, "xsd:int"]};
