const ts = Date.now() - (24 * 60 * 60 * 1000);
const declared = declare("Tags.netking_provision_ok", { value: ts });

let result = "UNKNOWN";
for (const item of declared) {
    if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
    const raw = String(item.value[0]).trim().toLowerCase();
    if (raw === "true" || raw === "1") {
        result = "OK";
        break;
    }
    if (raw === "false" || raw === "0") {
        result = "FAILED";
        break;
    }
}

return { writable: false, value: [result, "xsd:string"] };
