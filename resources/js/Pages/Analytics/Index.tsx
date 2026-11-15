import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, BellRing, CalendarCheck2, CircleDollarSign, TrendingUp, UserRoundCheck, UsersRound, XCircle } from 'lucide-react';

type Metrics = {
    appointments: number;
    confirmed: number;
    cancelled: number;
    cancellation_rate: number;
    lead_conversion_rate: number;
    estimated_revenue_cents: number;
    reminders_sent: number;
    followups_sent: number;
};
type Day = { date: string; label: string; confirmed: number; cancelled: number };
type Service = { name: string; appointments: number; confirmed: number; revenue_cents: number };
type Pipeline = { status: string; count: number };
type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    hasWorkspace: boolean;
    range: number;
    timezone?: string;
    metrics: Metrics | null;
    dailyVolume: Day[];
    services: Service[];
    pipeline: Pipeline[];
};

const money = (cents: number) => (cents / 100).toLocaleString('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 });
const statusColors: Record<string, string> = { new: 'bg-blue-500', lead: 'bg-amber-500', qualified: 'bg-violet-500', booked: 'bg-emerald-500', completed: 'bg-slate-500', lost: 'bg-rose-500' };

export default function AnalyticsIndex({ hasWorkspace, range, timezone, metrics, dailyVolume, services, pipeline }: PageProps) {
    const { t } = useTranslation();
    const maxDaily = Math.max(1, ...dailyVolume.map((day) => day.confirmed + day.cancelled));
    const maxService = Math.max(1, ...services.map((service) => service.appointments));
    const pipelineTotal = pipeline.reduce((total, item) => total + item.count, 0);
    const cards = metrics ? [
        { label: t('analytics.appointments', 'Appointments'), value: metrics.appointments.toLocaleString(), detail: `${metrics.confirmed} ${t('analytics.confirmed', 'confirmed').toLowerCase()}`, icon: CalendarCheck2, color: 'bg-emerald-50 text-emerald-700' },
        { label: t('analytics.estimatedRevenue', 'Estimated revenue'), value: money(metrics.estimated_revenue_cents), detail: t('analytics.servicePrices', 'Based on service prices'), icon: CircleDollarSign, color: 'bg-blue-50 text-blue-700' },
        { label: t('analytics.leadConversion', 'Lead conversion'), value: `${metrics.lead_conversion_rate}%`, detail: t('analytics.bookedCompleted', 'Booked or completed'), icon: UserRoundCheck, color: 'bg-violet-50 text-violet-700' },
        { label: t('analytics.cancellationRate', 'Cancellation rate'), value: `${metrics.cancellation_rate}%`, detail: `${metrics.cancelled} ${t('analytics.cancelled', 'cancelled').toLowerCase()}`, icon: XCircle, color: 'bg-rose-50 text-rose-700' },
    ] : [];

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('analytics.performance', 'Performance')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('analytics.title', 'Analytics')}</h1></div>}>
        <Head title={t('analytics.title', 'Analytics')} />
        <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {!hasWorkspace || !metrics ? <section className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"><BarChart3 className="mx-auto text-slate-300" size={38} /><h2 className="mt-4 font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h2><p className="mt-2 text-sm text-slate-500">{t('analytics.setupCopy', 'Analytics appear after your workspace starts receiving conversations and bookings.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></section> : <div className="grid gap-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><h2 className="text-lg font-semibold text-slate-900">{t('analytics.business', 'Business performance')}</h2><p className="mt-1 text-sm text-slate-500">{t('analytics.reporting', 'Reporting in')} {timezone?.replaceAll('_', ' ')}</p></div><div className="inline-flex w-fit rounded-xl border border-slate-200 bg-white p-1 shadow-sm">{[7, 30, 90].map((days) => <button key={days} onClick={() => router.get(route('analytics.index'), { range: days }, { preserveState: true, replace: true })} className={`rounded-lg px-4 py-2 text-xs font-bold ${range === days ? 'bg-slate-900 text-white' : 'text-slate-500 hover:bg-slate-50'}`}>{days} {t('common.days', 'days')}</button>)}</div></div>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{cards.map(({ label, value, detail, icon: Icon, color }) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start justify-between gap-4"><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</p><p className="mt-2 text-2xl font-semibold text-slate-900">{value}</p><p className="mt-1 text-xs text-slate-500">{detail}</p></div><span className={`grid h-10 w-10 shrink-0 place-items-center rounded-xl ${color}`}><Icon size={18} /></span></div></article>)}</section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex items-start justify-between gap-4"><div><h2 className="font-semibold text-slate-900">{t('analytics.volume', 'Appointment volume')}</h2><p className="mt-1 text-xs text-slate-500">{t('analytics.volumeCopy', 'Confirmed versus cancelled bookings by day.')}</p></div><div className="flex gap-3 text-[10px] font-bold uppercase tracking-wide"><span className="flex items-center gap-1.5 text-emerald-700"><i className="h-2 w-2 rounded-full bg-emerald-500" />{t('analytics.confirmed', 'Confirmed')}</span><span className="flex items-center gap-1.5 text-rose-700"><i className="h-2 w-2 rounded-full bg-rose-400" />{t('analytics.cancelled', 'Cancelled')}</span></div></div><div className="mt-6 overflow-x-auto pb-2"><div className="flex h-56 min-w-[680px] items-end gap-1.5 border-b border-slate-200 px-1">{dailyVolume.map((day, index) => <div key={day.date} className="group flex h-full min-w-0 flex-1 flex-col justify-end"><div className="relative flex flex-1 items-end justify-center gap-0.5"><div style={{ height: `${Math.max(day.confirmed ? 4 : 0, (day.confirmed / maxDaily) * 100)}%` }} className="w-2/5 rounded-t bg-emerald-500 transition group-hover:bg-emerald-600" /><div style={{ height: `${Math.max(day.cancelled ? 4 : 0, (day.cancelled / maxDaily) * 100)}%` }} className="w-2/5 rounded-t bg-rose-400 transition group-hover:bg-rose-500" /><span className="pointer-events-none absolute bottom-full z-10 mb-2 hidden whitespace-nowrap rounded-lg bg-slate-900 px-2 py-1 text-[10px] text-white group-hover:block">{day.label}: {day.confirmed} {t('analytics.confirmed', 'confirmed').toLowerCase()}, {day.cancelled} {t('analytics.cancelled', 'cancelled').toLowerCase()}</span></div>{(range <= 30 || index % 7 === 0) && <span className="mt-2 -rotate-45 whitespace-nowrap text-[9px] text-slate-400">{day.label}</span>}</div>)}</div></div></section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-700"><TrendingUp size={18} /></span><div><h2 className="font-semibold text-slate-900">{t('analytics.popular', 'Popular services')}</h2><p className="mt-1 text-xs text-slate-500">{t('analytics.popularCopy', 'Bookings and estimated revenue in this period.')}</p></div></div>{services.length ? <div className="mt-6 grid gap-4">{services.slice(0, 8).map((service) => <div key={service.name}><div className="flex items-center justify-between gap-4 text-sm"><span className="truncate font-semibold text-slate-700">{service.name}</span><span className="shrink-0 text-xs text-slate-500">{service.appointments} {t('customers.bookings', 'bookings')} · {money(service.revenue_cents)}</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div style={{ width: `${(service.appointments / maxService) * 100}%` }} className="h-full rounded-full bg-blue-500" /></div></div>)}</div> : <p className="mt-6 text-sm text-slate-500">{t('analytics.noServices', 'No service bookings in this period.')}</p>}</section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><UsersRound size={18} /></span><div><h2 className="font-semibold text-slate-900">{t('analytics.pipeline', 'Lead pipeline')}</h2><p className="mt-1 text-xs text-slate-500">{t('analytics.pipelineCopy', 'Current customer lifecycle across the workspace.')}</p></div></div><div className="mt-6 grid gap-4">{pipeline.map((item) => <div key={item.status}><div className="flex items-center justify-between text-sm"><span className="font-semibold capitalize text-slate-700">{t(`customers.${item.status}`, item.status)}</span><span className="text-xs text-slate-500">{item.count} · {pipelineTotal ? Math.round((item.count / pipelineTotal) * 100) : 0}%</span></div><div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div style={{ width: `${pipelineTotal ? (item.count / pipelineTotal) * 100 : 0}%` }} className={`h-full rounded-full ${statusColors[item.status]}`} /></div></div>)}</div></section>
                </div>

                <section className="grid gap-4 sm:grid-cols-2"><article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-amber-50 text-amber-700"><BellRing size={18} /></span><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{t('analytics.reminders', 'Reminders delivered')}</p><p className="mt-1 text-2xl font-semibold text-slate-900">{metrics.reminders_sent}</p></div></div></article><article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><TrendingUp size={18} /></span><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{t('analytics.followups', 'Follow-ups delivered')}</p><p className="mt-1 text-2xl font-semibold text-slate-900">{metrics.followups_sent}</p></div></div></article></section>
            </div>}
        </main>
    </AuthenticatedLayout>;
}
