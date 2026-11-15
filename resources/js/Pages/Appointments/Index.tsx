import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck2, CalendarPlus2, CalendarX2, CheckCircle2, Clock3, Mail, Pencil, Phone, UserRound, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Appointment = {
    public_id: string;
    customer_name: string;
    customer_phone: string;
    customer_email: string | null;
    service: string | null;
    starts_at: string;
    ends_at: string;
    timezone: string;
    status: string;
    notes: string | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    appointments: { data: Appointment[]; links: PaginationLink[]; total: number } | null;
    workspace: { timezone: string; duration: number; calendar_connected: boolean; now: string } | null;
    flash: { success?: string; error?: string };
};

const paginationLabel = (label: string) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
const formatDate = (value: string, timezone: string, locale: string) => new Intl.DateTimeFormat(locale, {
    weekday: 'short', month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZone: timezone,
}).format(new Date(value));

function AppointmentCard({ appointment }: { appointment: Appointment }) {
    const { language, t } = useTranslation();
    const form = useForm({ starts_at: '' });
    const [isEditing, setIsEditing] = useState(false);
    const isConfirmed = appointment.status === 'confirmed' && new Date(appointment.starts_at) > new Date();
    const reschedule = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('appointments.reschedule', appointment.public_id), { preserveScroll: true, onSuccess: () => setIsEditing(false) });
    };

    return <article className={`rounded-2xl border bg-white p-5 shadow-sm ${appointment.status === 'cancelled' ? 'border-slate-200 opacity-70' : 'border-slate-200'}`}>
        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div className="flex gap-4"><span className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${appointment.status === 'cancelled' ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700'}`}><CalendarCheck2 size={20} /></span><div><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-slate-900">{appointment.customer_name}</h3><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${appointment.status === 'cancelled' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700'}`}>{t(`appointments.${appointment.status}`, appointment.status)}</span></div><p className="mt-1 text-sm font-medium text-slate-600">{appointment.service ?? t('appointments.appointment', 'Appointment')}</p><div className="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500"><span className="inline-flex items-center gap-1.5"><Clock3 size={14} />{formatDate(appointment.starts_at, appointment.timezone, language === 'es' ? 'es-US' : 'en-US')}</span><span className="inline-flex items-center gap-1.5"><Phone size={14} />{appointment.customer_phone}</span>{appointment.customer_email && <span className="inline-flex items-center gap-1.5"><Mail size={14} />{appointment.customer_email}</span>}</div></div></div>
            {isConfirmed && <div className="flex gap-2"><button type="button" onClick={() => setIsEditing(!isEditing)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50"><Pencil size={14} /> {t('appointments.reschedule', 'Reschedule')}</button><button type="button" onClick={() => window.confirm(t('appointments.cancelConfirm', 'Cancel this appointment and remove it from Google Calendar?')) && router.delete(route('appointments.cancel', appointment.public_id), { preserveScroll: true })} className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50"><CalendarX2 size={14} /> {t('appointments.cancel', 'Cancel')}</button></div>}
        </div>
        {isEditing && <form onSubmit={reschedule} className="mt-4 flex flex-col gap-3 rounded-xl bg-violet-50 p-4 sm:flex-row sm:items-end"><label className="grid flex-1 gap-2 text-xs font-bold text-violet-900">{t('appointments.newStart', 'New start time')}<input type="datetime-local" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} className="rounded-lg border-violet-200 text-sm focus:border-violet-500 focus:ring-violet-500" />{form.errors.starts_at && <span className="font-medium text-rose-600">{form.errors.starts_at}</span>}</label><div className="flex gap-2"><button disabled={form.processing} className="rounded-lg bg-violet-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-violet-700">{t('appointments.saveTime', 'Save new time')}</button><button type="button" onClick={() => setIsEditing(false)} className="rounded-lg p-2.5 text-violet-500 hover:bg-violet-100" aria-label={t('appointments.close', 'Close')}><X size={16} /></button></div></form>}
        {appointment.notes && <p className="mt-4 border-t border-slate-100 pt-4 text-sm leading-6 text-slate-500">{appointment.notes}</p>}
    </article>;
}

export default function AppointmentsIndex({ appointments, workspace }: PageProps) {
    const { t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const form = useForm({ customer_name: '', customer_phone: '', customer_email: '', service: '', starts_at: '', notes: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('appointments.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('appointments.schedule', 'Schedule')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('appointments.title', 'Appointments')}</h1></div>}>
            <Head title={t('appointments.title', 'Appointments')} />
            <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(320px,.75fr)] lg:px-8">
                <section className="grid content-start gap-5">
                    {(flash.success || flash.error) && <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}
                    <div className="flex items-center justify-between gap-4"><div><h2 className="text-lg font-semibold text-slate-900">{t('appointments.all', 'All bookings')}</h2><p className="mt-1 text-sm text-slate-500">{appointments?.total ?? 0} {t('appointments.count', 'appointments in this workspace')}</p></div><span className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700">{t('appointments.protection', 'Google conflict protection')}</span></div>

                    {!workspace ? <div className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"><CalendarCheck2 className="mx-auto text-slate-300" size={36} /><h3 className="mt-4 font-semibold text-slate-900">{t('appointments.setupFirst', 'Set up your workspace first')}</h3><p className="mt-2 text-sm text-slate-500">{t('appointments.setupCopy', 'Business hours and a calendar are required before booking.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></div> : appointments?.data.length ? <div className="grid gap-3">{appointments.data.map((appointment) => <AppointmentCard key={appointment.public_id} appointment={appointment} />)}</div> : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><CalendarCheck2 className="mx-auto text-slate-300" size={38} /><h3 className="mt-4 font-semibold text-slate-900">{t('appointments.none', 'No appointments yet')}</h3><p className="mt-2 text-sm text-slate-500">{t('appointments.noneCopy', 'Create a test booking to exercise the same conflict-safe flow the WhatsApp bot will use.')}</p></div>}

                    {appointments && appointments.links.length > 3 && <nav className="flex justify-center gap-1">{appointments.links.map((link, index) => link.url ? <Link key={index} href={link.url} preserveScroll className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-slate-900 font-bold text-white' : 'text-slate-500 hover:bg-white'}`}>{paginationLabel(link.label)}</Link> : <span key={index} className="px-3 py-2 text-sm text-slate-300">{paginationLabel(link.label)}</span>)}</nav>}
                </section>

                <aside className="lg:sticky lg:top-6 lg:self-start">
                    <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><CalendarPlus2 size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('appointments.test', 'Create test booking')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('appointments.testCopy', 'Checks local and Google availability before creating the event.')}</p></div></div>
                        {!workspace?.calendar_connected && <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-800">{t('appointments.connectGoogle', 'Connect Google Calendar in Setup before creating appointments.')}</div>}
                        <div className="mt-6 grid gap-4">
                            <label className="grid gap-2 text-sm font-semibold text-slate-700"><span className="flex items-center gap-2"><UserRound size={15} />{t('appointments.customerName', 'Customer name')}</span><input value={form.data.customer_name} onChange={(event) => form.setData('customer_name', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />{form.errors.customer_name && <span className="text-xs text-rose-600">{form.errors.customer_name}</span>}</label>
                            <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('appointments.phone', 'Phone')}<input value={form.data.customer_phone} onChange={(event) => form.setData('customer_phone', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="+1 555 123 4567" />{form.errors.customer_phone && <span className="text-xs text-rose-600">{form.errors.customer_phone}</span>}</label>
                            <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('appointments.email', 'Email')} <span className="font-normal text-slate-400">{t('common.optional', 'optional')}</span><input type="email" value={form.data.customer_email} onChange={(event) => form.setData('customer_email', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></label>
                            <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('appointments.service', 'Service')}<input value={form.data.service} onChange={(event) => form.setData('service', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('services.namePlaceholder', 'Initial consultation')} /></label>
                            <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('appointments.start', 'Start time')}<input type="datetime-local" value={form.data.starts_at} onChange={(event) => form.setData('starts_at', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />{workspace && <span className="text-xs font-normal text-slate-400">{workspace.duration} {t('common.minutes', 'minutes')} · {workspace.timezone.replaceAll('_', ' ')}</span>}{form.errors.starts_at && <span className="text-xs leading-5 text-rose-600">{form.errors.starts_at}</span>}</label>
                            <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('appointments.notes', 'Notes')} <span className="font-normal text-slate-400">{t('common.optional', 'optional')}</span><textarea rows={3} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></label>
                        </div>
                        <button disabled={form.processing || !workspace?.calendar_connected} className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"><CalendarPlus2 size={17} />{form.processing ? t('appointments.checking', 'Checking availability…') : t('appointments.book', 'Book appointment')}</button>
                    </form>
                </aside>
            </main>
        </AuthenticatedLayout>
    );
}
