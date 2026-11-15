import ApplicationLogo from '@/Components/ApplicationLogo';
import LanguageSwitcher from '@/Components/LanguageSwitcher';
import { useTranslation } from '@/i18n';
import { Link } from '@inertiajs/react';
import { CalendarCheck2, Check, MessageCircleMore } from 'lucide-react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    const { t } = useTranslation();
    const benefits = [t('guest.point1', 'Answer customers around the clock'), t('guest.point2', 'Offer only times that are truly available'), t('guest.point3', 'Keep every booking organized')];

    return <div className="min-h-screen bg-[#f4f7f5] lg:grid lg:grid-cols-[minmax(0,.9fr)_minmax(520px,1.1fr)]">
        <aside className="relative hidden overflow-hidden bg-[#102b22] p-12 text-white lg:flex lg:flex-col lg:justify-between xl:p-16">
            <div className="absolute -left-20 top-1/3 h-72 w-72 rounded-full bg-emerald-400/15 blur-3xl" />
            <Link href="/" className="relative flex items-center gap-3"><span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-500 text-emerald-950"><ApplicationLogo className="h-6 w-6 fill-current" /></span><span><strong className="block text-base font-bold">Bookline AI</strong><small className="block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-300">{t('common.whatsappBooking', 'WhatsApp booking')}</small></span></Link>
            <div className="relative max-w-lg py-14"><span className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-300">{t('guest.eyebrow', 'A calmer way to book')}</span><h1 className="mt-5 text-4xl font-semibold leading-tight tracking-[-0.04em] xl:text-5xl">{t('guest.title', 'Every conversation can become a confirmed appointment.')}</h1><p className="mt-5 text-base leading-7 text-emerald-50/70">{t('guest.copy', 'Connect WhatsApp, your services, and your calendar in one focused workspace.')}</p><div className="mt-8 grid gap-4 text-sm text-emerald-50/85">{benefits.map((item) => <div key={item} className="flex items-center gap-3"><span className="grid h-6 w-6 place-items-center rounded-full bg-emerald-400/15 text-emerald-300"><Check size={14} /></span>{item}</div>)}</div></div>
            <div className="relative flex items-center gap-4 rounded-2xl border border-white/10 bg-white/[0.06] p-4"><span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-400/15 text-emerald-300"><MessageCircleMore size={19} /></span><div><p className="text-sm font-semibold">{t('guest.tools', 'WhatsApp + Calendar')}</p><p className="mt-0.5 text-xs text-emerald-50/55">{t('guest.toolsCopy', 'One booking assistant, always in sync')}</p></div><CalendarCheck2 className="ml-auto text-emerald-300" size={20} /></div>
        </aside>

        <main className="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:px-12">
            <div className="w-full max-w-md">
                <Link href="/" className="mb-8 flex items-center justify-center gap-3 lg:hidden"><span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-600 text-white"><ApplicationLogo className="h-6 w-6 fill-current" /></span><span><strong className="block text-base font-bold text-slate-950">Bookline AI</strong><small className="block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-600">{t('common.whatsappBooking', 'WhatsApp booking')}</small></span></Link>
                <div className="mb-4 flex justify-end"><LanguageSwitcher compact /></div>
                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_20px_60px_rgba(20,48,38,.08)] sm:p-8">{children}</div>
                <p className="mt-6 text-center text-xs text-slate-400">{t('guest.secure', 'Secure booking workspace powered by Bookline AI')}</p>
            </div>
        </main>
    </div>;
}
