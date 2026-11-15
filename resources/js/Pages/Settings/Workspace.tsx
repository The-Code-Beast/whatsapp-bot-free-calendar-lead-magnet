import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Bot, CalendarDays, CheckCircle2, Clock3, ExternalLink, Globe2, Save, Unplug } from 'lucide-react';
import { FormEvent } from 'react';

type BusinessHour = {
    day_of_week: number;
    opens_at: string | null;
    closes_at: string | null;
    is_enabled: boolean;
};

type Workspace = {
    name: string;
    timezone: string;
    appointment_duration_minutes: number;
    buffer_minutes: number;
    bot_tone: string;
    assistant_language: string;
    bot_instructions: string | null;
    business_hours: BusinessHour[];
};

type CalendarConnection = {
    email: string | null;
    calendar_id: string | null;
    calendar_name: string | null;
    status: string;
};

type CalendarOption = {
    id: string;
    summary: string;
    primary: boolean;
    access_role: string;
};

type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    workspace: Workspace | null;
    calendarConnection: CalendarConnection | null;
    calendars: CalendarOption[];
    calendarError: string | null;
    googleConfigured: boolean;
    timezones: string[];
    flash: { success?: string; error?: string };
};

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

const defaultHours: BusinessHour[] = dayNames.map((_, day) => ({
    day_of_week: day,
    opens_at: day === 0 || day === 6 ? null : '09:00',
    closes_at: day === 0 || day === 6 ? null : '17:00',
    is_enabled: day !== 0 && day !== 6,
}));

const normalizeTime = (time: string | null) => time?.slice(0, 5) ?? '';

export default function WorkspaceSettings({ workspace, calendarConnection, calendars, calendarError, googleConfigured, timezones }: PageProps) {
    const { t } = useTranslation();
    const localizedDayNames = [t('workspace.sunday', 'Sunday'), t('workspace.monday', 'Monday'), t('workspace.tuesday', 'Tuesday'), t('workspace.wednesday', 'Wednesday'), t('workspace.thursday', 'Thursday'), t('workspace.friday', 'Friday'), t('workspace.saturday', 'Saturday')];
    const { flash } = usePage<PageProps>().props;
    const workspaceForm = useForm({
        name: workspace?.name ?? '',
        timezone: workspace?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone ?? 'America/Los_Angeles',
        appointment_duration_minutes: workspace?.appointment_duration_minutes ?? 30,
        buffer_minutes: workspace?.buffer_minutes ?? 0,
        bot_tone: workspace?.bot_tone ?? 'friendly',
        assistant_language: workspace?.assistant_language ?? 'auto',
        bot_instructions: workspace?.bot_instructions ?? '',
        business_hours: workspace?.business_hours.length
            ? workspace.business_hours.map((hours) => ({ ...hours, opens_at: normalizeTime(hours.opens_at), closes_at: normalizeTime(hours.closes_at) }))
            : defaultHours,
    });
    const calendarForm = useForm({ calendar_id: calendarConnection?.calendar_id ?? '' });

    const submitWorkspace = (event: FormEvent) => {
        event.preventDefault();
        workspaceForm.put(route('workspace.update'), { preserveScroll: true });
    };

    const submitCalendar = (event: FormEvent) => {
        event.preventDefault();
        calendarForm.patch(route('google-calendar.update'), { preserveScroll: true });
    };

    const updateHours = (index: number, values: Partial<BusinessHour>) => {
        workspaceForm.setData('business_hours', workspaceForm.data.business_hours.map((hours, currentIndex) => currentIndex === index ? { ...hours, ...values } : hours));
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('workspace.setup', 'Setup')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('workspace.title', 'Workspace & calendar')}</h1></div>}>
            <Head title={t('workspace.title', 'Workspace settings')} />
            <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(320px,.75fr)] lg:px-8">
                <div className="grid gap-6">
                    {(flash.success || flash.error) && (
                        <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}>
                            <CheckCircle2 size={18} /> {flash.success ?? flash.error}
                        </div>
                    )}

                    <form onSubmit={submitWorkspace} className="grid gap-6">
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><Globe2 size={19} /></span><div><h2 className="text-lg font-semibold text-slate-900">{t('workspace.businessDetails', 'Business details')}</h2><p className="mt-1 text-sm text-slate-500">{t('workspace.businessDetailsCopy', 'These settings control how every booking is scheduled.')}</p></div></div>
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.businessName', 'Business name')}<input value={workspaceForm.data.name} onChange={(event) => workspaceForm.setData('name', event.target.value)} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Acme Dental" />{workspaceForm.errors.name && <span className="text-xs font-medium text-rose-600">{workspaceForm.errors.name}</span>}</label>
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.timezone', 'Timezone')}<select value={workspaceForm.data.timezone} onChange={(event) => workspaceForm.setData('timezone', event.target.value)} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{timezones.map((timezone) => <option key={timezone} value={timezone}>{timezone.replaceAll('_', ' ')}</option>)}</select>{workspaceForm.errors.timezone && <span className="text-xs font-medium text-rose-600">{workspaceForm.errors.timezone}</span>}</label>
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.duration', 'Appointment duration')}<select value={workspaceForm.data.appointment_duration_minutes} onChange={(event) => workspaceForm.setData('appointment_duration_minutes', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{[15, 20, 30, 45, 60, 90, 120].map((minutes) => <option key={minutes} value={minutes}>{minutes} {t('common.minutes', 'minutes')}</option>)}</select></label>
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.buffer', 'Buffer between bookings')}<select value={workspaceForm.data.buffer_minutes} onChange={(event) => workspaceForm.setData('buffer_minutes', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{[0, 5, 10, 15, 30, 60].map((minutes) => <option key={minutes} value={minutes}>{minutes === 0 ? t('workspace.noBuffer', 'No buffer') : `${minutes} ${t('common.minutes', 'minutes')}`}</option>)}</select></label>
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-sky-50 text-sky-700"><Clock3 size={19} /></span><div><h2 className="text-lg font-semibold text-slate-900">{t('workspace.businessHours', 'Business hours')}</h2><p className="mt-1 text-sm text-slate-500">{t('workspace.businessHoursCopy', 'The assistant only offers times inside these hours.')}</p></div></div>
                            <div className="mt-6 divide-y divide-slate-100">
                                {workspaceForm.data.business_hours.map((hours, index) => (
                                    <div key={hours.day_of_week} className="grid items-center gap-3 py-3 sm:grid-cols-[130px_1fr]">
                                        <label className="flex items-center gap-3 text-sm font-semibold text-slate-700"><input type="checkbox" checked={hours.is_enabled} onChange={(event) => updateHours(index, { is_enabled: event.target.checked })} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />{localizedDayNames[hours.day_of_week]}</label>
                                        {hours.is_enabled ? <div className="flex items-center gap-2"><input type="time" value={hours.opens_at ?? ''} onChange={(event) => updateHours(index, { opens_at: event.target.value })} className="min-w-0 flex-1 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /><span className="text-xs font-medium text-slate-400">{t('workspace.to', 'to')}</span><input type="time" value={hours.closes_at ?? ''} onChange={(event) => updateHours(index, { closes_at: event.target.value })} className="min-w-0 flex-1 rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></div> : <span className="text-sm text-slate-400">{t('workspace.closed', 'Closed')}</span>}
                                        {(workspaceForm.errors[`business_hours.${index}.opens_at`] || workspaceForm.errors[`business_hours.${index}.closes_at`]) && <span className="text-xs font-medium text-rose-600 sm:col-start-2">{workspaceForm.errors[`business_hours.${index}.opens_at`] ?? workspaceForm.errors[`business_hours.${index}.closes_at`]}</span>}
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><Bot size={19} /></span><div><h2 className="text-lg font-semibold text-slate-900">{t('workspace.guidance', 'Assistant guidance')}</h2><p className="mt-1 text-sm text-slate-500">{t('workspace.guidanceCopy', 'Give the bot context for natural, on-brand conversations.')}</p></div></div>
                            <div className="mt-6 grid gap-5">
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.tone', 'Response tone')}<select value={workspaceForm.data.bot_tone} onChange={(event) => workspaceForm.setData('bot_tone', event.target.value)} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="friendly">{t('workspace.friendly', 'Friendly')}</option><option value="professional">{t('workspace.professional', 'Professional')}</option><option value="concise">{t('workspace.concise', 'Concise')}</option><option value="warm">{t('workspace.warm', 'Warm')}</option></select></label>
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.assistantLanguage', 'Assistant language')}<select value={workspaceForm.data.assistant_language} onChange={(event) => workspaceForm.setData('assistant_language', event.target.value)} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="auto">{t('workspace.languageAuto', 'Automatic detection')}</option><option value="en">{t('language.english', 'English')}</option><option value="es">{t('language.spanish', 'Spanish')}</option></select><span className="text-xs font-normal leading-5 text-slate-400">{t('workspace.assistantLanguageCopy', 'Automatic detection remembers the language each customer starts with.')}</span></label>
                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.instructions', 'Booking instructions')}<textarea value={workspaceForm.data.bot_instructions} onChange={(event) => workspaceForm.setData('bot_instructions', event.target.value)} rows={5} className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('workspace.instructionsPlaceholder', 'Describe your services, what the assistant should ask, and anything customers should know before booking.')} /><span className="text-xs font-normal text-slate-400">{workspaceForm.data.bot_instructions.length} / 5,000 {t('workspace.characters', 'characters')}</span>{workspaceForm.errors.bot_instructions && <span className="text-xs font-medium text-rose-600">{workspaceForm.errors.bot_instructions}</span>}</label>
                            </div>
                        </section>

                        <div className="flex justify-end"><button type="submit" disabled={workspaceForm.processing} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"><Save size={17} />{workspaceForm.processing ? t('workspace.saving', 'Saving…') : t('workspace.save', 'Save workspace')}</button></div>
                    </form>
                </div>

                <aside className="lg:sticky lg:top-6 lg:self-start">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-700"><CalendarDays size={19} /></span><div><h2 className="font-semibold text-slate-900">Google Calendar</h2><p className="mt-1 text-xs text-slate-500">{t('workspace.calendarCopy', 'Check conflicts and create events.')}</p></div></div>{calendarConnection?.status === 'connected' && <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">{t('common.connected', 'Connected')}</span>}</div>
                        {!workspace ? <div className="mt-6 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-800">{t('workspace.saveFirst', 'Save your business settings first, then connect Google Calendar.')}</div> : !calendarConnection ? <div className="mt-6"><p className="text-sm leading-6 text-slate-600">{t('workspace.authorize', 'Authorize access so the assistant can see availability and create confirmed bookings.')}</p>{googleConfigured ? <a href={route('google-calendar.redirect')} className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white hover:bg-slate-800">{t('workspace.connectGoogle', 'Connect Google Calendar')} <ExternalLink size={16} /></a> : <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-5 text-amber-800">{t('workspace.googleEnv', 'Add the Google OAuth client ID and secret to the environment before connecting.')}</div>}</div> : <div className="mt-6 grid gap-5"><div className="rounded-xl bg-slate-50 p-4"><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{t('workspace.googleAccount', 'Google account')}</p><p className="mt-1 truncate text-sm font-semibold text-slate-800">{calendarConnection.email ?? t('workspace.connectedAccount', 'Connected account')}</p></div>{calendarError ? <p className="rounded-xl bg-rose-50 p-3 text-xs leading-5 text-rose-700">{calendarError}</p> : <form onSubmit={submitCalendar} className="grid gap-3"><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('workspace.bookingCalendar', 'Booking calendar')}<select value={calendarForm.data.calendar_id} onChange={(event) => calendarForm.setData('calendar_id', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">{calendars.map((calendar) => <option key={calendar.id} value={calendar.id}>{calendar.summary}{calendar.primary ? ` (${t('workspace.primary', 'Primary')})` : ''}</option>)}</select></label>{calendarForm.errors.calendar_id && <span className="text-xs text-rose-600">{calendarForm.errors.calendar_id}</span>}<button disabled={calendarForm.processing} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">{t('workspace.saveCalendar', 'Save calendar')}</button></form>}<Link href={route('google-calendar.destroy')} method="delete" as="button" className="inline-flex items-center justify-center gap-2 text-xs font-semibold text-slate-500 hover:text-rose-600"><Unplug size={14} /> {t('workspace.disconnect', 'Disconnect account')}</Link></div>}
                    </section>
                    <Link href={route('dashboard')} className="mt-4 flex items-center justify-center text-sm font-semibold text-slate-500 hover:text-slate-800">{t('workspace.back', 'Back to dashboard')}</Link>
                </aside>
            </main>
        </AuthenticatedLayout>
    );
}
