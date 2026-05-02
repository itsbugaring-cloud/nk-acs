const ts = Date.now() - (24 * 60 * 60 * 1000);
const declared = declare("Tags.netking_bootstrap_seen", { value: ts });

let status = "PENDING";
for (const item of declared) {
    if (!item || !item.value || item.value[0] === null || item.value[0] === undefined) continue;
    const raw = String(item.value[0]).trim().toLowerCase();
    if (raw === "true" || raw === "1") {
        status = "BOOTSTRAPPED";
        break;
    }
}

return { writable: false, value: [status, "xsd:string"] };
