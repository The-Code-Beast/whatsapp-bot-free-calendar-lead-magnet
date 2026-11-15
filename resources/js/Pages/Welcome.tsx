import ApplicationLogo from '@/Components/ApplicationLogo';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useTranslation } from '@/i18n';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Bot, CalendarCheck2, Check, MessageCircleMore, Sparkles } from 'lucide-react';

export default function Welcome({ auth }: PageProps) {
    const { t } = useTranslation();
    const features = [
        { icon: MessageCircleMore, title: t('welcome.feature1', 'Book from WhatsApp'), copy: t('welcome.feature1copy', 'Turn customer conversations into confirmed appointments without leaving the chat.') },
        { icon: CalendarCheck2, title: t('welcome.feature2', 'Stay in sync'), copy: t('welcome.feature2copy', 'Check live availability and add every confirmed booking to your connected calendar.') },
        { icon: Bot, title: t('welcome.feature3', 'Always available'), copy: t('welcome.feature3copy', 'Keep your booking flow moving while you focus on the people already in front of you.') },
    ];

    return <>
        <Head title="WhatsApp booking assistant" />
        <div className="min-h-screen overflow-hidden bg-[#f4f7f5] text-slate-950">
            <header className="relative z-20 mx-auto flex max-w-7xl items-center justify-between gap-6 px-5 py-6 sm:px-8 lg:px-10">
                <Link href="/" className="flex items-center gap-3">
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-600 text-white shadow-lg shadow-emerald-900/15"><ApplicationLogo className="h-6 w-6 fill-current" /></span>
                    <span><strong className="block text-base font-bold tracking-tight">Bookline AI</strong><small className="block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-600">{t('common.whatsappBooking', 'WhatsApp booking')}</small></span>
                </Link>
                <nav className="flex items-center gap-2">
                    <LanguageSwitcher compact />
                    {auth.user ? <Link href={route('dashboard')} className="inline-flex items-center gap-2 rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">{t('welcome.dashboard', 'Open dashboard')} <ArrowRight size={16} /></Link> : <>
                        <Link href={route('login')} className="hidden rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-white hover:text-slate-950 sm:block">{t('welcome.login', 'Log in')}</Link>
                        <Link href={route('register')} className="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-700">{t('welcome.start', 'Get started')}</Link>
                    </>}
                </nav>
            </header>

            <main>
                <section className="relative mx-auto grid max-w-7xl items-center gap-12 px-5 pb-20 pt-12 sm:px-8 lg:grid-cols-[1.05fr_.95fr] lg:px-10 lg:pb-28 lg:pt-20">
                    <div className="relative z-10">
                        <span className="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 shadow-sm"><Sparkles size={14} /> {t('welcome.badge', 'Your front desk, available around the clock')}</span>
                        <h1 className="mt-6 max-w-3xl text-5xl font-semibold leading-[1.02] tracking-[-0.055em] text-slate-950 sm:text-6xl lg:text-7xl">{t('welcome.title', 'Turn WhatsApp chats into booked appointments.')}</h1>
                        <p className="mt-6 max-w-2xl text-lg leading-8 text-slate-600">{t('welcome.copy', 'Bookline connects your WhatsApp Business number, services, availability, and calendar in one simple booking workspace.')}</p>
                        <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                            <Link href={auth.user ? route('dashboard') : route('register')} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-700">{auth.user ? t('welcome.dashboard', 'Open dashboard') : t('welcome.create', 'Create your workspace')} <ArrowRight size={17} /></Link>
                            {!auth.user && <Link href={route('login')} className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-3.5 text-sm font-bold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">{t('welcome.account', 'I already have an account')}</Link>}
                        </div>
                        <div className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium text-slate-500">{[t('welcome.noBackAndForth', 'No scheduling back-and-forth'), t('welcome.calendarAware', 'Calendar-aware'), t('welcome.forTeams', 'Built for service teams')].map((item) => <span key={item} className="flex items-center gap-2"><span className="grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-emerald-700"><Check size={12} /></span>{item}</span>)}</div>
                    </div>

                    <div className="relative mx-auto w-full max-w-xl">
                        <div className="absolute -inset-12 rounded-full bg-emerald-200/40 blur-3xl" />
                        <div className="relative overflow-hidden rounded-[2rem] border border-white/80 bg-white p-4 shadow-[0_30px_90px_rgba(15,43,32,.16)] sm:p-6">
                            <div className="rounded-2xl bg-[#102b22] p-5 text-white">
                                <div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-full bg-emerald-500"><MessageCircleMore size={20} /></span><div><p className="text-sm font-bold">{t('welcome.assistant', 'Bookline Assistant')}</p><p className="text-xs text-emerald-200">{t('welcome.online', 'Online now')}</p></div></div>
                                <div className="mt-8 grid gap-3">
                                    <div className="max-w-[82%] rounded-2xl rounded-tl-sm bg-white/10 p-4 text-sm leading-6 text-emerald-50">{t('welcome.message1', 'Hi! What service would you like to book?')}</div>
                                    <div className="ml-auto max-w-[78%] rounded-2xl rounded-tr-sm bg-emerald-500 p-4 text-sm font-medium text-emerald-950">{t('welcome.message2', 'A 60-minute consultation next Tuesday.')}</div>
                                    <div className="max-w-[88%] rounded-2xl rounded-tl-sm bg-white/10 p-4 text-sm leading-6 text-emerald-50">{t('welcome.message3', 'I found 10:00 AM and 2:30 PM. Which works best?')}</div>
                                </div>
                            </div>
                            <div className="mt-4 flex items-center gap-4 rounded-2xl border border-emerald-100 bg-emerald-50 p-4"><span className="grid h-11 w-11 place-items-center rounded-xl bg-white text-emerald-600 shadow-sm"><CalendarCheck2 size={21} /></span><div><p className="text-xs font-bold uppercase tracking-wide text-emerald-700">{t('welcome.confirmed', 'Booking confirmed')}</p><p className="mt-1 text-sm font-semibold text-slate-900">{t('welcome.time', 'Tuesday at 10:00 AM')}</p></div><Check className="ml-auto text-emerald-600" size={20} /></div>
                        </div>
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-white/70">
                    <div className="mx-auto grid max-w-7xl gap-5 px-5 py-16 sm:px-8 md:grid-cols-3 lg:px-10">{features.map(({ icon: Icon, title, copy }) => <article key={title} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-600"><Icon size={21} /></span><h2 className="mt-5 text-lg font-bold tracking-tight">{title}</h2><p className="mt-2 text-sm leading-6 text-slate-600">{copy}</p></article>)}</div>
                </section>
            </main>
        </div>
    </>;
}
