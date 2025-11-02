import { setupMiddleware } from '@/middleware';
import '../css/style.css';

/**
 * Auth module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.log('Auth module loaded');

    setupMiddleware();
}

/**
 * Auth module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.log('Auth module after mount logic executed');
}