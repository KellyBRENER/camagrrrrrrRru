// API/DB values are not trusted URLs. Generated photos have one fixed path format.
export function photoUrl(path) {
    return typeof path === "string" && /^uploads\/photos\/[0-9]+\/[0-9]+\.png$/.test(path)
        ? `/${path}` : "/images/favicon.png";
}
