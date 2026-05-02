const DEFAULT_TARGET = "NOT_SET";

if ("value" in args[1] && args[1].value && args[1].value[0]) {
    const existing = String(args[1].value[0]).trim();
    if (existing !== "") {
        return { writable: true, value: [existing, "xsd:string"] };
    }
}

return { writable: true, value: [DEFAULT_TARGET, "xsd:string"] };
