import '../css/style.css';
import { setupAuthMiddleware } from './middleware/auth';

/**
 * Auth module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('Auth module loaded');

    setupAuthMiddleware();
}

/**
 * Auth module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('Auth module after mount logic executed');
}