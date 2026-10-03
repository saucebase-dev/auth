import type { ComponentType } from 'react';
import IconGithub from '~icons/simple-icons/github';
import IconGoogle from '~icons/simple-icons/google';

/** Brand marks for the social sign-in providers, keyed by provider name. */
export const providerIcons: Record<
    string,
    ComponentType<{ className?: string }>
> = {
    google: IconGoogle,
    github: IconGithub,
};
