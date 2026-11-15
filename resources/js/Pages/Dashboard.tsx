import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Bot, CalendarDays, Check, ChevronRight, Clock3, MessageCircleMore, MoreHorizontal, Phone, Settings2, Sparkles, UsersRound } from 'lucide-react';

type DashboardProps = {
    stats: {
        bookings_this_month: number;
        active_conversations: number;
        hours_saved: number;
    };
    setup: {
        workspace: boolean;
        whatsapp: boolean;
        calendar: boolean;
        assistant: boolean;
    };
};

export default function Dashboard({ stats, setup }: DashboardProps) {
    const { t } = useTranslation();
    const statCards = [
        { label: t('dashboard.bookingsMonth', 'Bookings this month'), value: String(stats.bookings_this_month), note: setup.calendar ? t('dashboard.calendarSynced', 'Synced with your booking calendar') : t('dashboard.connectCalendar', 'Connect your calendar to begin'), icon: CalendarDays },
        { label: t('dashboard.active', 'Active conversations'), value: String(stats.active_conversations), note: setup.whatsapp ? t('dashboard.live', 'Live WhatsApp conversations') : t('dashboard.messagesHere', 'Messages will appear here'), icon: MessageCircleMore },
        { label: t('dashboard.hours', 'Hours saved'), value: `${stats.hours_saved}h`, note: t('dashboard.hoursNote', 'Calculated from completed bookings'), icon: Clock3 },
    ];
    const setupSteps = [
        { title: t('dashboard.createWorkspace', 'Create your workspace'), description: t('dashboard.workspaceCopy', 'Set business hours and booking rules.'), done: setup.workspace, icon: UsersRound, href: route('workspace.edit') },
        { title: t('dashboard.connectWhatsApp', 'Connect WhatsApp'), description: t('dashboard.whatsappCopy', 'Link an official business number with Meta.'), done: setup.whatsapp, icon: Phone, href: route('whatsapp.edit') },
        { title: t('dashboard.connectGoogle', 'Connect Google Calendar'), description: t('dashboard.googleCopy', 'Choose where confirmed bookings are created.'), done: setup.calendar, icon: CalendarDays, href: route('workspace.edit') },
        { title: t('dashboard.addServices', 'Add your services'), description: t('dashboard.servicesCopy', 'Set bookable services, durations, and prices.'), done: setup.assistant, icon: Bot, href: route('services.index') },
    ];
    const completedSteps = setupSteps.filter((step) => step.done).length;
    const assistantReady = Object.values(setup).every(Boolean);

    return (
        <AuthenticatedLayout>
            <Head title={t('dashboard.title', 'Dashboard')} />
            <main className="dashboard-shell">
                <section className="hero-panel">
                    <div>
                        <span className="eyebrow"><Sparkles size={14} /> {t('dashboard.assistant', 'Booking assistant')}</span>
                        <h1>{t('dashboard.greeting', 'Good morning. Let’s get your assistant ready.')}</h1>
                        <p>{t('dashboard.intro', 'Connect WhatsApp and your calendar, then your assistant can turn conversations into confirmed appointments around the clock.')}</p>
                    </div>
                    <Link href={route('workspace.edit')} className="primary-action">{t('dashboard.continue', 'Continue setup')} <ArrowUpRight size={17} /></Link>
                </section>

                <section className="stats-grid">
                    {statCards.map(({ label, value, note, icon: Icon }) => (
                        <article className="stat-card" key={label}>
                            <div className="stat-icon"><Icon size={19} /></div>
                            <div className="stat-value">{value}</div><div className="stat-label">{label}</div><div className="stat-note">{note}</div>
                        </article>
                    ))}
                </section>

                <section className="dashboard-grid">
                    <article className="panel setup-panel">
                        <div className="panel-heading"><div><span className="section-kicker">{t('dashboard.getStarted', 'GET STARTED')}</span><h2>{t('dashboard.complete', 'Complete your setup')}</h2></div><span className="progress-copy">{completedSteps} {t('dashboard.of', 'of')} 4</span></div>
                        <div className="progress-track"><span style={{ width: `${completedSteps * 25}%` }} /></div>
                        <div className="setup-list">
                            {setupSteps.map(({ title, description, done, icon: Icon, href }) => (
                                <Link href={href} className={`setup-item ${done ? 'is-done' : ''}`} key={title}>
                                    <span className="setup-icon">{done ? <Check size={18} /> : <Icon size={18} />}</span>
                                    <span><strong>{title}</strong><small>{description}</small></span><ChevronRight size={18} className="setup-chevron" />
                                </Link>
                            ))}
                        </div>
                    </article>

                    <article className="panel assistant-panel">
                        <div className="panel-heading"><div><span className="section-kicker">{t('dashboard.assistant', 'ASSISTANT')}</span><h2>{t('dashboard.status', 'Booking status')}</h2></div><button className="icon-button"><MoreHorizontal size={19} /></button></div>
                        <div className="bot-orbit"><div className="bot-avatar"><Bot size={34} /></div><span className="status-dot" /></div>
                        <h3>{assistantReady ? t('dashboard.ready', 'Your assistant is ready') : t('dashboard.paused', 'Your assistant is paused')}</h3><p>{assistantReady ? t('dashboard.readyCopy', 'All connections are healthy and ready for booking conversations.') : t('dashboard.pausedCopy', 'Finish the connection steps before turning on automatic replies.')}</p>
                        <div className="connection-list">
                            <Link href={route('whatsapp.edit')}><span className="connection-icon whatsapp"><MessageCircleMore size={17} /></span><span>WhatsApp</span><em>{setup.whatsapp ? t('dashboard.connected', 'Connected') : t('dashboard.notConnected', 'Not connected')}</em></Link>
                            <div><span className="connection-icon calendar"><CalendarDays size={17} /></span><span>Google Calendar</span><em>{setup.calendar ? t('dashboard.connected', 'Connected') : t('dashboard.notConnected', 'Not connected')}</em></div>
                        </div>
                        <Link href={route('workspace.edit')} className="secondary-action"><Settings2 size={16} /> {t('dashboard.configure', 'Configure assistant')}</Link>
                    </article>
                </section>

                <section className="panel empty-panel">
                    <div className="panel-heading"><div><span className="section-kicker">{t('dashboard.schedule', 'SCHEDULE')}</span><h2>{t('dashboard.upcoming', 'Upcoming appointments')}</h2></div><Link href={route('appointments.index')} className="text-action">{t('dashboard.viewCalendar', 'View calendar')} <ChevronRight size={16} /></Link></div>
                    <div className="empty-state"><div><CalendarDays size={25} /></div><h3>{t('dashboard.none', 'No appointments yet')}</h3><p>{t('dashboard.noneCopy', 'Your confirmed WhatsApp bookings will show up here automatically.')}</p></div>
                </section>
            </main>
        </AuthenticatedLayout>
    );
}
