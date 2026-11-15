import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Ban, CalendarClock, CheckCircle2, Clock3, Save, Settings2, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

type BusinessHour = {
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_enabled: boolean;
};

type EditableBusinessHour = Omit<BusinessHour, 'opens_at' | 'closes_at'> & {
    opens_at: string;
    closes_at: string;
};

type ServiceSchedule = {
    id: number;
    name: string;
    uses_custom_hours: boolean;
    business_hours: BusinessHour[];
};

type AvailabilityBlock = {
    id: number;
    starts_at: string;
    ends_at: string;
    reason: string | null;
};

type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    workspace: { timezone: string; buffer_minutes: number; business_hours: BusinessHour[] } | null;
    blocks: AvailabilityBlock[];
    services: ServiceSchedule[];
    flash: { success?: string; error?: string };
};

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const defaultHours: BusinessHour[] = dayNames.map((_, day) => ({
    day_of_week: day,
    opens_at: day > 0 && day < 6 ? '09:00' : null,
    closes_at: day > 0 && day < 6 ? '17:00' : null,
    is_enabled: day > 0 && day < 6,
}));
const normalizeHours = (hours: BusinessHour[]): EditableBusinessHour[] => defaultHours.map((fallback) => {
    const saved = hours.find((item) => item.day_of_week === fallback.day_of_week) ?? fallback;
    return { ...saved, opens_at: saved.opens_at?.slice(0, 5) ?? '', closes_at: saved.closes_at?.slice(0, 5) ?? '' };
});

function HoursEditor({ hours, onChange, disabled = false }: { hours: EditableBusinessHour[]; onChange: (hours: EditableBusinessHour[]) => void; disabled?: boolean }) {
    const { t } = useTranslation();
    const localizedDayNames = [t('workspace.sunday', 'Sunday'), t('workspace.monday', 'Monday'), t('workspace.tuesday', 'Tuesday'), t('workspace.wednesday', 'Wednesday'), t('workspace.thursday', 'Thursday'), t('workspace.friday', 'Friday'), t('workspace.saturday', 'Saturday')];
    const update = (index: number, values: Partial<EditableBusinessHour>) => onChange(hours.map((item, current) => current === index ? { ...item, ...values } : item));

    return <div className="grid gap-3">{hours.map((hoursForDay, index) => (
        <div key={hoursForDay.day_of_week} className="grid gap-3 rounded-xl border border-slate-200 p-3 sm:grid-cols-[150px_1fr_1fr] sm:items-center">
            <label className="flex items-center gap-3 text-sm font-semibold text-slate-700">
                <input type="checkbox" checked={hoursForDay.is_enabled} disabled={disabled} onChange={(event) => update(index, { is_enabled: event.target.checked })} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                {localizedDayNames[hoursForDay.day_of_week]}
            </label>
            <input type="time" value={hoursForDay.opens_at ?? ''} disabled={disabled || !hoursForDay.is_enabled} onChange={(event) => update(index, { opens_at: event.target.value })} className="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-50" />
            <input type="time" value={hoursForDay.closes_at ?? ''} disabled={disabled || !hoursForDay.is_enabled} onChange={(event) => update(index, { closes_at: event.target.value })} className="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-50" />
        </div>
    ))}</div>;
}

function ServiceScheduleEditor({ service, workspaceHours }: { service: ServiceSchedule; workspaceHours: BusinessHour[] }) {
    const { t } = useTranslation();
    const form = useForm({
        uses_custom_hours: service.uses_custom_hours,
        business_hours: normalizeHours(service.business_hours.length ? service.business_hours : workspaceHours),
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('service-availability.update', service.id), { preserveScroll: true });
    };

    return <form onSubmit={submit} className="grid gap-4">
        <label className="flex items-start gap-3 rounded-xl bg-violet-50 p-4 text-sm text-violet-900">
            <input type="checkbox" checked={form.data.uses_custom_hours} onChange={(event) => form.setData('uses_custom_hours', event.target.checked)} className="mt-0.5 rounded border-violet-300 text-violet-600 focus:ring-violet-500" />
            <span><strong className="block">{t('availability.customSchedule', 'Use a custom schedule for')} {service.name}</strong><span className="mt-1 block text-xs leading-5 text-violet-700">{t('availability.customScheduleCopy', 'When off, this service follows the main weekly hours.')}</span></span>
        </label>
        <HoursEditor hours={form.data.business_hours} onChange={(hours) => form.setData('business_hours', hours)} disabled={!form.data.uses_custom_hours} />
        {Object.values(form.errors).length > 0 && <p className="text-xs font-medium text-rose-600">{t('availability.review', 'Review the enabled days and make sure each closing time is after its opening time.')}</p>}
        <div className="flex justify-end"><button disabled={form.processing} className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-60"><Save size={16} /> {t('availability.saveService', 'Save service schedule')}</button></div>
    </form>;
}

export default function AvailabilityEdit({ workspace, blocks, services }: PageProps) {
    const { language, t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const [selectedServiceId, setSelectedServiceId] = useState<number | null>(services[0]?.id ?? null);
    const selectedService = services.find((service) => service.id === selectedServiceId) ?? null;
    const availabilityForm = useForm({
        buffer_minutes: workspace?.buffer_minutes ?? 0,
        business_hours: normalizeHours(workspace?.business_hours ?? []),
    });
    const blockForm = useForm({ starts_at: '', ends_at: '', reason: '' });

    const saveAvailability = (event: FormEvent) => {
        event.preventDefault();
        availabilityForm.put(route('availability.update'), { preserveScroll: true });
    };
    const addBlock = (event: FormEvent) => {
        event.preventDefault();
        blockForm.post(route('availability-blocks.store'), { preserveScroll: true, onSuccess: () => blockForm.reset() });
    };
    const formatLocal = (value: string) => new Intl.DateTimeFormat(language === 'es' ? 'es-US' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('appointments.schedule', 'Schedule')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('availability.title', 'Availability')}</h1></div>}>
        <Head title={t('availability.title', 'Availability')} />
        <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(320px,.65fr)] lg:px-8">
            {!workspace ? <section className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm lg:col-span-2"><CalendarClock className="mx-auto text-slate-300" size={38} /><h2 className="mt-4 font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h2><p className="mt-2 text-sm text-slate-500">{t('availability.setupCopy', 'Availability uses your business timezone and booking settings.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></section> : <>
                <div className="grid gap-6">
                    {(flash.success || flash.error) && <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}
                    <form onSubmit={saveAvailability} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><Clock3 size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('availability.weekly', 'Weekly hours')}</h2><p className="mt-1 text-sm text-slate-500">{t('availability.timesUse', 'Times use')} {workspace.timezone.replaceAll('_', ' ')}.</p></div></div>
                        <div className="mt-6"><HoursEditor hours={availabilityForm.data.business_hours} onChange={(hours) => availabilityForm.setData('business_hours', hours)} /></div>
                        <div className="mt-5 grid gap-2 sm:max-w-xs"><label className="text-sm font-semibold text-slate-700">{t('availability.buffer', 'Buffer between appointments')}</label><select value={availabilityForm.data.buffer_minutes} onChange={(event) => availabilityForm.setData('buffer_minutes', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">{[0, 5, 10, 15, 30, 60].map((minutes) => <option key={minutes} value={minutes}>{minutes === 0 ? t('workspace.noBuffer', 'No buffer') : `${minutes} ${t('common.minutes', 'minutes')}`}</option>)}</select></div>
                        {Object.values(availabilityForm.errors).length > 0 && <p className="mt-4 text-xs font-medium text-rose-600">{t('availability.review', 'Review the enabled days and make sure each closing time is after its opening time.')}</p>}
                        <div className="mt-6 flex justify-end"><button disabled={availabilityForm.processing} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"><Save size={16} /> {t('availability.save', 'Save availability')}</button></div>
                    </form>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><Settings2 size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('availability.serviceSchedules', 'Service schedules')}</h2><p className="mt-1 text-sm text-slate-500">{t('availability.serviceSchedulesCopy', 'Optionally limit a service to its own weekly hours.')}</p></div></div>
                        {services.length ? <div className="mt-6 grid gap-5"><select value={selectedServiceId ?? ''} onChange={(event) => setSelectedServiceId(Number(event.target.value))} className="rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500">{services.map((service) => <option key={service.id} value={service.id}>{service.name}</option>)}</select>{selectedService && <ServiceScheduleEditor key={selectedService.id} service={selectedService} workspaceHours={availabilityForm.data.business_hours} />}</div> : <p className="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">{t('availability.addServices', 'Add services before configuring service-specific hours.')}</p>}
                    </section>
                </div>

                <aside className="grid content-start gap-6">
                    <form onSubmit={addBlock} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-rose-50 text-rose-700"><Ban size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('availability.block', 'Block time')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('availability.blockCopy', 'Vacations, holidays, or one-off closures.')}</p></div></div>
                        <div className="mt-5 grid gap-4"><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('availability.starts', 'Starts')}<input type="datetime-local" value={blockForm.data.starts_at} onChange={(event) => blockForm.setData('starts_at', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500" />{blockForm.errors.starts_at && <span className="text-xs text-rose-600">{blockForm.errors.starts_at}</span>}</label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('availability.ends', 'Ends')}<input type="datetime-local" value={blockForm.data.ends_at} onChange={(event) => blockForm.setData('ends_at', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500" />{blockForm.errors.ends_at && <span className="text-xs text-rose-600">{blockForm.errors.ends_at}</span>}</label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('availability.reason', 'Reason')} <span className="font-normal text-slate-400">({t('common.optional', 'optional')})</span><input value={blockForm.data.reason} onChange={(event) => blockForm.setData('reason', event.target.value)} placeholder={t('availability.reasonPlaceholder', 'Holiday closure')} className="rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500" /></label></div>
                        <button disabled={blockForm.processing} className="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-800 disabled:opacity-60"><Ban size={16} /> {t('availability.addBlock', 'Add blocked time')}</button>
                    </form>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h2 className="font-semibold text-slate-900">{t('availability.upcomingBlocks', 'Upcoming blocks')}</h2>{blocks.length ? <div className="mt-4 grid gap-3">{blocks.map((block) => <article key={block.id} className="rounded-xl border border-slate-200 p-4"><div className="flex justify-between gap-3"><div><p className="text-sm font-semibold text-slate-800">{block.reason || t('availability.unavailable', 'Unavailable')}</p><p className="mt-1 text-xs leading-5 text-slate-500">{formatLocal(block.starts_at)}<br />{t('workspace.to', 'to')} {formatLocal(block.ends_at)}</p></div><button type="button" onClick={() => window.confirm(t('availability.removeConfirm', 'Remove this blocked time?')) && router.delete(route('availability-blocks.destroy', block.id), { preserveScroll: true })} className="text-slate-400 hover:text-rose-600" aria-label={t('availability.remove', 'Remove blocked time')}><Trash2 size={17} /></button></div></article>)}</div> : <p className="mt-4 text-sm text-slate-500">{t('availability.noBlocks', 'No blocked dates yet.')}</p>}</section>
                </aside>
            </>}
        </main>
    </AuthenticatedLayout>;
}
