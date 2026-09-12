import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Plus, Trophy } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/hooks/use-translations';
import { dashboard } from '@/routes';
import {
    create as competitionsCreate,
    index as competitionsIndex,
} from '@/routes/competitions';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useTranslations();
    const { auth } = usePage().props;

    const mainNavItems: NavItem[] =
        auth.user?.role === 'admin'
            ? [
                  {
                      title: t('Dashboard'),
                      href: dashboard(),
                      icon: LayoutGrid,
                  },
                  {
                      title: t('Competitions'),
                      href: competitionsIndex(),
                      icon: Trophy,
                  },
                  {
                      title: t('New competition'),
                      href: competitionsCreate(),
                      icon: Plus,
                  },
              ]
            : [];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
