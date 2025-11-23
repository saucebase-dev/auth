import type { User } from '@/types';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

export const useAuthStore = defineStore(
    'modules/auth',
    () => {
        const user = ref<User | null>(null);

        // Computed
        const isAuthenticated = computed(() => !!user.value);

        // Actions
        const setUser = (userData: User) => {
            user.value = userData;
        };

        const clearUser = () => {
            user.value = null;
        };

        return {
            user,
            isAuthenticated,
            setUser,
            clearUser,
        };
    },
    {
        persist: false,
    },
);
