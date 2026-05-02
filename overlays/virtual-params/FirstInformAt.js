if ("value" in args[1] && args[1].value && args[1].value[0]) {
    return { writable: false, value: [args[1].value[0], "xsd:string"] };
}

const nowIso = new Date(Date.now()).toISOString();
return { writable: false, value: [nowIso, "xsd:string"] };
