import { describe, expect, it } from 'vitest';
import { confirmedFor, isConfirmed } from './passwordConfirmation';

const now = 1_000_000_000_000;

describe('isConfirmed', () => {
    it('is not without a confirmation', () => {
        expect(isConfirmed(null, now)).toBe(false);
        expect(isConfirmed(undefined, now)).toBe(false);
    });

    it('is until shortly before the server stops trusting it', () => {
        const until = now / 1000 + 600;

        expect(isConfirmed(until, now)).toBe(true);
        // The browser's clock may run behind the server's: ask a little early.
        expect(isConfirmed(until, (until - 20) * 1000)).toBe(false);
        expect(isConfirmed(until, (until + 1) * 1000)).toBe(false);
    });
});

describe('confirmedFor', () => {
    it('is the timeout from now, in seconds', () => {
        expect(confirmedFor(10800, now)).toBe(now / 1000 + 10800);
    });
});
