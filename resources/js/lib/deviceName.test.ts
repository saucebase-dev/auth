import { describe, expect, it } from 'vitest';
import { deviceName } from './deviceName';

describe('deviceName', () => {
    it.each([
        [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'Chrome on macOS',
        ],
        [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15',
            'Safari on macOS',
        ],
        [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0',
            'Edge on Windows',
        ],
        [
            'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0',
            'Firefox on Linux',
        ],
        [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
            'Safari on iPhone',
        ],
        [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0.0.0 Mobile/15E148 Safari/604.1',
            'Chrome on iPhone',
        ],
        [
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36',
            'Chrome on Android',
        ],
        [
            'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            'Chrome on ChromeOS',
        ],
    ])('names %s', (userAgent, expected) => {
        expect(deviceName(userAgent)).toBe(expected);
    });

    it('falls back to whichever half it recognizes', () => {
        expect(deviceName('Mozilla/5.0 (Windows NT 10.0) Unknown/1.0')).toBe(
            'Windows',
        );
        expect(deviceName('Firefox/131.0')).toBe('Firefox');
    });

    it('gives up on an agent it cannot read', () => {
        expect(deviceName('curl/8.0')).toBeNull();
        expect(deviceName('')).toBeNull();
    });
});
