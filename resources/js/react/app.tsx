import { confirm } from '@/hooks/useDialog';
import { trans } from '@/i18n';
import { registerGlobalComponent } from '@/lib/globalComponents';
import { registerAction, registerIcon } from '@/lib/navigation';
import { router } from '@inertiajs/react';
import { LogOut } from 'lucide-react';
import IconLogOut from '~icons/lucide/log-out';
import IconSettings from '~icons/lucide/settings';
import IconShieldCheck from '~icons/lucide/shield-check';
import IconUserCircle from '~icons/lucide/user-circle';
import ImpersonationAlert from './components/ImpersonationAlert';

export function setup() {
    registerIcon('logout', IconLogOut);
    registerIcon('settings', IconSettings);
    registerIcon('profile', IconUserCircle);
    registerIcon('security', IconShieldCheck);
    registerAuthActions();
    registerGlobalComponent('top', ImpersonationAlert);
}

function registerAuthActions() {
    registerAction('logout', async (event: MouseEvent) => {
        event.preventDefault();

        const confirmed = await confirm({
            title: trans('Log out'),
            description: trans(
                'Are you sure you want to log out? You will need to sign in again.',
            ),
            confirmLabel: trans('Log out'),
            cancelLabel: trans('Cancel'),
            variant: 'destructive',
            icon: LogOut,
            align: 'left',
        });

        if (confirmed) {
            router.post(route('logout'));
        }
    });
}

export function afterMount() {}
