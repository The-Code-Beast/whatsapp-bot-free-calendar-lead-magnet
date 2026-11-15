import ApplicationLogo from '@/Components/ApplicationLogo';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useTranslation } from '@/i18n';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    BellRing,
    CalendarCheck2,
    CalendarClock,
    ChevronDown,
    LayoutDashboard,
    LogOut,
    Menu,
    MessageCircleMore,
    Scissors,
    Settings2,
    UserRound,
    UsersRound,
    X,
    type LucideIcon,
} from 'lucide-react';
import { PropsWithChildren, ReactNode, useEffect, useState } from 'react';

type NavigationItem = {
    label: string;
    href: string;
    icon: LucideIcon;
    active: boolean;
};

function SidebarLink({ item, onNavigate, nested = false }: { item: NavigationItem; onNavigate: () => void; nested?: boolean }) {
    const Icon = item.icon;

    return <Link
        href={item.href}
        onClick={onNavigate}
        className={`group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition ${nested ? 'ms-4' : ''} ${item.active ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-900/10' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'}`}
    >
        <Icon size={18} strokeWidth={item.active ? 2.25 : 1.8} className={item.active ? 'text-white' : 'text-slate-400 transition group-hover:text-slate-700'} />
        <span>{item.label}</span>
    </Link>;
}

export default function Authenticated({ header, children }: PropsWithChildren<{ header?: ReactNode }>) {
    const user = usePage().props.auth.user;
    const { t } = useTranslation();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const settingsActive = Boolean(route().current('workspace.*') || route().current('whatsapp.*') || route().current('profile.*'));
    const [settingsOpen, setSettingsOpen] = useState(settingsActive);
    const closeSidebar = () => setSidebarOpen(false);

    useEffect(() => {
        const desktopNavigation = window.matchMedia('(min-width: 1280px)');
        const closeDrawerAtDesktopWidth = (event: MediaQueryListEvent) => {
            if (event.matches) {
                setSidebarOpen(false);
            }
        };

        desktopNavigation.addEventListener('change', closeDrawerAtDesktopWidth);

        return () => desktopNavigation.removeEventListener('change', closeDrawerAtDesktopWidth);
    }, []);

    const sections: { label: string; items: NavigationItem[] }[] = [
        {
            label: t('nav.overview', 'Overview'),
            items: [
                { label: t('nav.dashboard', 'Dashboard'), href: route('dashboard'), icon: LayoutDashboard, active: Boolean(route().current('dashboard')) },
                { label: t('nav.analytics', 'Analytics'), href: route('analytics.index'), icon: BarChart3, active: Boolean(route().current('analytics.*')) },
            ],
        },
        {
            label: t('nav.bookings', 'Bookings'),
            items: [
                { label: t('nav.appointments', 'Appointments'), href: route('appointments.index'), icon: CalendarCheck2, active: Boolean(route().current('appointments.*')) },
                { label: t('nav.customers', 'Customers'), href: route('customers.index'), icon: UsersRound, active: Boolean(route().current('customers.*')) },
                { label: t('nav.conversations', 'Conversations'), href: route('conversations.index'), icon: MessageCircleMore, active: Boolean(route().current('conversations.*')) },
            ],
        },
        {
            label: t('nav.setup', 'Booking setup'),
            items: [
                { label: t('nav.services', 'Services'), href: route('services.index'), icon: Scissors, active: Boolean(route().current('services.*')) },
                { label: t('nav.availability', 'Availability'), href: route('availability.edit'), icon: CalendarClock, active: Boolean(route().current('availability*') || route().current('service-availability.*')) },
                { label: t('nav.automations', 'Automations'), href: route('automations.edit'), icon: BellRing, active: Boolean(route().current('automations.*')) },
            ],
        },
    ];
    const settingsItems: NavigationItem[] = [
        { label: t('nav.workspace', 'Workspace & calendar'), href: route('workspace.edit'), icon: CalendarClock, active: Boolean(route().current('workspace.*')) },
        { label: t('nav.whatsapp', 'WhatsApp connection'), href: route('whatsapp.edit'), icon: MessageCircleMore, active: Boolean(route().current('whatsapp.*')) },
        { label: t('nav.profile', 'Profile'), href: route('profile.edit'), icon: UserRound, active: Boolean(route().current('profile.*')) },
    ];

    const sidebar = <aside className={`fixed inset-y-0 left-0 z-50 flex w-[calc(100vw-2rem)] flex-col border-r border-slate-200 bg-white shadow-xl shadow-slate-900/5 transition-transform duration-200 ease-out sm:w-72 xl:sticky xl:top-0 xl:h-screen xl:shrink-0 xl:translate-x-0 xl:shadow-none ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="flex h-20 items-center justify-between border-b border-slate-100 px-5">
            <Link href={route('dashboard')} onClick={closeSidebar} className="flex items-center gap-3">
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-600 text-white shadow-sm shadow-emerald-900/20"><ApplicationLogo className="h-6 w-6 fill-current" /></span>
                <span><strong className="block text-base font-bold tracking-tight text-slate-950">Bookline AI</strong><small className="mt-0.5 block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-600">{t('common.whatsappBooking', 'WhatsApp booking')}</small></span>
            </Link>
            <button type="button" onClick={closeSidebar} className="grid h-9 w-9 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 xl:hidden" aria-label={t('nav.close', 'Close navigation')}><X size={20} /></button>
        </div>

        <nav className="flex-1 overflow-y-auto px-4 py-5">
            <div className="grid gap-6">
                {sections.map((section) => <section key={section.label}>
                    <p className="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{section.label}</p>
                    <div className="grid gap-1">{section.items.map((item) => <SidebarLink key={item.label} item={item} onNavigate={closeSidebar} />)}</div>
                </section>)}
                <section>
                    <button type="button" onClick={() => setSettingsOpen((open) => !open)} className={`flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition ${settingsActive ? 'bg-slate-100 text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'}`} aria-expanded={settingsOpen}>
                        <Settings2 size={18} className={settingsActive ? 'text-emerald-600' : 'text-slate-400'} />
                        <span className="flex-1 text-left">{t('nav.settings', 'Settings')}</span>
                        <ChevronDown size={16} className={`text-slate-400 transition-transform ${settingsOpen ? 'rotate-180' : ''}`} />
                    </button>
                    {settingsOpen && <div className="mt-1 grid gap-1 border-l border-slate-200 pl-1">{settingsItems.map((item) => <SidebarLink key={item.label} item={item} onNavigate={closeSidebar} nested />)}</div>}
                </section>
            </div>
        </nav>

        <div className="border-t border-slate-100 p-4">
            <div className="mb-3"><LanguageSwitcher /></div>
            <div className="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700">{user.name.slice(0, 1).toUpperCase()}</span>
                <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-slate-900">{user.name}</p><p className="truncate text-xs text-slate-500">{user.email}</p></div>
                <Link href={route('logout')} method="post" as="button" className="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-white hover:text-rose-600" aria-label={t('nav.logout', 'Log out')}><LogOut size={16} /></Link>
            </div>
        </div>
    </aside>;

    return <div className="min-h-screen bg-[#f4f7f5] xl:flex">
        {sidebar}
        {sidebarOpen && <button type="button" onClick={closeSidebar} className="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-sm xl:hidden" aria-label={t('nav.closeOverlay', 'Close navigation overlay')} />}

        <div className="min-h-screen min-w-0 flex-1">
            <div className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur xl:hidden">
                <button type="button" onClick={() => setSidebarOpen(true)} className="grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-slate-600 shadow-sm" aria-label={t('nav.open', 'Open navigation')}><Menu size={20} /></button>
                <Link href={route('dashboard')} className="flex items-center gap-2"><span className="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-white"><ApplicationLogo className="h-5 w-5 fill-current" /></span><strong className="text-sm text-slate-900">Bookline AI</strong></Link>
                <span className="h-10 w-10" aria-hidden="true" />
            </div>

            {header && <header className="border-b border-slate-200 bg-white/90 shadow-sm shadow-slate-900/[0.02]"><div className="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">{header}</div></header>}
            <main>{children}</main>
        </div>
    </div>;
}
