import type { User } from '@/types';

declare module '@inertiajs/core' {
    interface PageProps {
        auth: {
            user: User | null;
            last_social_provider?: string | null;
            magic_link_enabled?: boolean;
            registration_enabled?: boolean;
            modal_enabled?: boolean;
            socialite_providers?: Array<{
                name: string;
                label: string;
            }>;
        };
    }
}

export {};
