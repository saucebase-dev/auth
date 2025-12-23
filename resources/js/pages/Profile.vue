<script setup lang="ts">
import SettingsSidebar from '@/components/Navigation/SettingsSidebar.vue';
import Avatar from '@/components/ui/avatar/Avatar.vue';
import AvatarFallback from '@/components/ui/avatar/AvatarFallback.vue';
import AvatarImage from '@/components/ui/avatar/AvatarImage.vue';
import Button from '@/components/ui/button/Button.vue';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardDescription from '@/components/ui/card/CardDescription.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Separator from '@/components/ui/separator/Separator.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const title = 'Profile';

const page = usePage<{ auth: { user: User } }>();
const user = computed(() => page.props.auth.user);

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
};

const formatDate = (date: string | null) => {
    if (!date) return 'Never';
    return new Date(date).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>
<template>
    <Head :title="$t(title)" />
    <AppLayout :title="title">
        <div class="flex h-full min-h-[calc(100vh-6rem)]">
            <!-- Settings Sidebar on the left -->
            <SettingsSidebar collapsible="none" class="shadow-md" />

            <!-- Page Content on the right -->
            <div class="flex-1 p-4 pt-2 pl-2">
                <div class="flex h-full flex-1 flex-col gap-6">
                    <div class="flex items-center justify-between">
                        <h1 class="text-2xl font-bold">{{ $t(title) }}</h1>
                    </div>

                    <div class="grid gap-6">
                        <!-- Profile Overview Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{
                                    $t('Profile Information')
                                }}</CardTitle>
                                <CardDescription>
                                    {{
                                        $t(
                                            'Your personal information and account details',
                                        )
                                    }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div
                                    class="flex flex-col gap-6 sm:flex-row sm:items-start"
                                >
                                    <!-- Avatar -->
                                    <div
                                        class="flex justify-center sm:justify-start"
                                    >
                                        <Avatar
                                            class="ring-border h-24 w-24 ring-2"
                                        >
                                            <AvatarImage
                                                :src="user.avatar"
                                                :alt="user.name"
                                            />
                                            <AvatarFallback class="text-2xl">
                                                {{ getInitials(user.name) }}
                                            </AvatarFallback>
                                        </Avatar>
                                    </div>

                                    <!-- User Details -->
                                    <div class="flex-1 space-y-4">
                                        <div class="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <div
                                                    class="text-muted-foreground text-sm font-medium"
                                                >
                                                    {{ $t('Name') }}
                                                </div>
                                                <div
                                                    class="mt-1 text-lg font-semibold"
                                                >
                                                    {{ user.name }}
                                                </div>
                                            </div>
                                            <div>
                                                <div
                                                    class="text-muted-foreground text-sm font-medium"
                                                >
                                                    {{ $t('Email') }}
                                                </div>
                                                <div
                                                    class="mt-1 text-lg font-semibold"
                                                >
                                                    {{ user.email }}
                                                </div>
                                            </div>
                                        </div>

                                        <Separator />

                                        <div>
                                            <div
                                                class="text-muted-foreground text-sm font-medium"
                                            >
                                                {{ $t('Last Login') }}
                                            </div>
                                            <div class="mt-1">
                                                {{
                                                    formatDate(
                                                        user.last_login_at,
                                                    )
                                                }}
                                            </div>
                                        </div>

                                        <div class="flex gap-2 pt-2">
                                            <Button variant="outline">
                                                {{ $t('Edit Profile') }}
                                            </Button>
                                            <Button variant="outline">
                                                {{ $t('Change Password') }}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Account Settings Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{
                                    $t('Account Settings')
                                }}</CardTitle>
                                <CardDescription>
                                    {{ $t('Manage your account preferences') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ $t('Email Notifications') }}
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{
                                                $t(
                                                    'Receive email updates about your account',
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        {{ $t('Configure') }}
                                    </Button>
                                </div>
                                <Separator />
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{
                                                $t('Two-Factor Authentication')
                                            }}
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{
                                                $t(
                                                    'Add an extra layer of security',
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        {{ $t('Setup') }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Security Card -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ $t('Security') }}</CardTitle>
                                <CardDescription>
                                    {{ $t('Keep your account secure') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ $t('Active Sessions') }}
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{
                                                $t(
                                                    'Manage your active sessions',
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm">
                                        {{ $t('View') }}
                                    </Button>
                                </div>
                                <Separator />
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">
                                            {{ $t('Delete Account') }}
                                        </div>
                                        <div
                                            class="text-muted-foreground text-sm"
                                        >
                                            {{
                                                $t(
                                                    'Permanently delete your account',
                                                )
                                            }}
                                        </div>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                    >
                                        {{ $t('Delete') }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
