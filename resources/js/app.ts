import { setupAuthMiddleware } from './middleware/auth';

// import { router } from '@inertiajs/vue3';
// import { useNavigationStore } from '@modules/Navigation/resources/js/stores';
// import { LogOut } from 'lucide-vue-next';
// import { useAuthStore } from './stores';

import '../css/style.css';

/**
 * Auth module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('Auth module loaded');

    setupAuthMiddleware();

    // Add Logout item to NavUser
    // useNavigationStore().addItem(
    //     {
    //         id: 'logout',
    //         type: 'action',
    //         title: 'Log out',
    //         icon: LogOut,
    //         priority: 0,
    //         action: () => {
    //             //TODO: i18n and better confirmation dialog
    //             if (!confirm('Are you sure you want to log out?')) {
    //                 return;
    //             }

    //             router.post(route('logout'));
    //             useAuthStore().clearUser();
    //         },
    //     },
    //     { area: 'user' },
    // );
}

/**
 * Auth module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('Auth module after mount logic executed');
}
