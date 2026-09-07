import AuthLayoutTemplate from '@/layouts/auth/auth-console-layout';
import type { AuthLayoutProps } from '@/types';

export default function AuthLayout({ children, ...props }: AuthLayoutProps) {
    return <AuthLayoutTemplate {...props}>{children}</AuthLayoutTemplate>;
}
