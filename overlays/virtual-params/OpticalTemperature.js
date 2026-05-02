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

            if (numeric >= 100 ) {
                return Math.round(numeric / 255);
            } else if (numeric < 100) {
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
        'InternetGatewayDevice.WANDevice.1.X_CT-COM_EponInterfaceConfig.TransceiverTemperature',
        'InternetGatewayDevice.WANDevice.1.X_CT-COM_GponInterfaceConfig.TransceiverTemperature',
        'InternetGatewayDevice.WANDevice.1.X_GponInterafceConfig.TransceiverTemperature',
        'InternetGatewayDevice.WANDevice.1.X_CMCC_EponInterfaceConfig.TransceiverTemperature',
        'InternetGatewayDevice.WANDevice.1.X_CMCC_GponInterfaceConfig.TransceiverTemperature',
        'InternetGatewayDevice.WANDevice.1.X_CU_WANEPONInterfaceConfig.OpticalTransceiver.Temperature',
      	'InternetGatewayDevice.WANDevice.1.X_ZTE-COM_WANPONInterfaceConfig.TransceiverTemperature',
      	'InternetGatewayDevice.WANDevice.1.X_FH_GponInterfaceConfig.TransceiverTemperature',
        'InternetGatewayDevice.X_ALU_OntOpticalParam.Temperature', // Nokia
        'InternetGatewayDevice.WANDevice.1.WANGponInterfaceConfig.TransceiverTemperature', // Generic GPON
        'InternetGatewayDevice.WANDevice.1.X_VSOL_PON_Interface.Temperature', // VSOL
        'InternetGatewayDevice.WANDevice.1.X_VSOL_Optical.Temperature', // VSOL Alt
        'InternetGatewayDevice.DeviceInfo.Temperature', // Generic device temperature
        'InternetGatewayDevice.X_Tenda_PON.Temperature', // Tenda
        'Device.Optical.Interface.1.Temperature', // TR-181
        'InternetGatewayDevice.WANDevice.1.X_Tenda_Optical.Temperature', // Tenda Alt
        'InternetGatewayDevice.WANDevice.1.X_C-Data_PON_Interface.Temperature' // C-Data
    ];

    result = getParameterValue(keys);
}

return {writable: false, value: [result, "xsd:int"]};
