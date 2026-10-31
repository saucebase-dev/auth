import { describe, expect, it } from 'vitest';
import { passkeyErrorMessage } from './passkeyError';

const t = (key: string) => `t:${key}`;

describe('passkeyErrorMessage', () => {
    it('says nothing without an error', () => {
        expect(passkeyErrorMessage(null, t)).toBeNull();
        expect(passkeyErrorMessage(undefined, t)).toBeNull();
    });

    it('says nothing when the user closed the prompt', () => {
        expect(
            passkeyErrorMessage(
                { name: 'UserCancelledError', message: 'Cancelled' },
                t,
            ),
        ).toBeNull();
    });

    it('points to another device when this one already has a passkey', () => {
        expect(
            passkeyErrorMessage(
                {
                    name: 'PasskeyExistsError',
                    message: 'This device is already registered as a passkey.',
                },
                t,
            ),
        ).toBe(
            't:This device already has a passkey. Add one from another device or a password manager.',
        );
    });

    it('passes any other error through', () => {
        expect(
            passkeyErrorMessage({ name: 'Error', message: 'Server error' }, t),
        ).toBe('Server error');
    });
});
