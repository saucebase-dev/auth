/**
 * A default passkey name from the browser's user agent, e.g. "Chrome on macOS", so
 * adding one doesn't stall on naming it. Order matters: Edge and Opera also say
 * Chrome, Chrome also says Safari, and iOS also says Mac OS X.
 */
const browsers: Array<[RegExp, string]> = [
    [/Edg(e|A|iOS)?\//, 'Edge'],
    [/OPR\/|Opera/, 'Opera'],
    [/Firefox\/|FxiOS\//, 'Firefox'],
    [/Chrome\/|CriOS\//, 'Chrome'],
    [/Safari\//, 'Safari'],
];

const systems: Array<[RegExp, string]> = [
    [/iPhone/, 'iPhone'],
    [/iPad/, 'iPad'],
    [/Android/, 'Android'],
    [/CrOS/, 'ChromeOS'],
    [/Windows/, 'Windows'],
    [/Mac OS X|Macintosh/, 'macOS'],
    [/Linux/, 'Linux'],
];

const match = (list: Array<[RegExp, string]>, userAgent: string) =>
    list.find(([pattern]) => pattern.test(userAgent))?.[1];

export function deviceName(userAgent: string): string | null {
    const browser = match(browsers, userAgent);
    const system = match(systems, userAgent);

    if (browser && system) return `${browser} on ${system}`;

    return browser ?? system ?? null;
}
