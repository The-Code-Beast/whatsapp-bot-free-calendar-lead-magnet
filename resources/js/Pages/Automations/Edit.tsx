import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { BellRing, CheckCircle2, Clock3, MessageCircleHeart, Save, Send } from 'lucide-react';
import { FormEvent } from 'react';

type Settings = {
    reminder_enabled: boolean;
    reminder_hours_before: number;
    reminder_message: string | null;
    followup_enabled: boolean;
    followup_hours_after: number;
    followup_message: string | null;
    timezone: string;
};

type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    settings: Settings | null;
    stats: { reminders_sent: number; followups_sent: number; upcoming: number };
    flash: { success?: string; error?: string };
};

const reminderDefault = 'Hi {name}, this is a reminder that your {service} appointment is on {date} at {time}. Reply RESCHEDULE to choose a new time or CANCEL to cancel.';
const followupDefault = 'Hi {name}, thank you for your {service} appointment. We hope everything went well. Reply here if you need any help.';

export default function AutomationsEdit({ settings, stats }: PageProps) {
    const { t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const form = useForm({
        reminder_enabled: settings?.reminder_enabled ?? true,
        reminder_hours_before: settings?.reminder_hours_before ?? 24,
        reminder_message: settings?.reminder_message ?? '',
        followup_enabled: settings?.followup_enabled ?? true,
        followup_hours_after: settings?.followup_hours_after ?? 2,
        followup_message: settings?.followup_message ?? '',
    });
    const statCards = [
        { label: t('automations.upcoming', 'Upcoming appointments'), value: stats.upcoming, icon: Clock3 },
        { label: t('automations.sentReminders', 'Reminders sent'), value: stats.reminders_sent, icon: BellRing },
        { label: t('automations.sentFollowups', 'Follow-ups sent'), value: stats.followups_sent, icon: Send },
    ];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('automations.update'), { preserveScroll: true });
    };

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('automations.engagement', 'Engagement')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('automations.title', 'Automations')}</h1></div>}>
        <Head title={t('automations.title', 'Automations')} />
        <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            {!settings ? <section className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm"><BellRing className="mx-auto text-slate-300" size={38} /><h2 className="mt-4 font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h2><p className="mt-2 text-sm text-slate-500">{t('automations.setupCopy', 'Reminders use your workspace timezone and WhatsApp connection.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></section> : <div className="grid gap-6">
                {(flash.success || flash.error) && <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}
                <section className="grid gap-4 sm:grid-cols-3">
                    {statCards.map(({ label, value, icon: Icon }) => <article key={label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-center justify-between gap-3"><div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{label}</p><p className="mt-2 text-3xl font-semibold text-slate-900">{value}</p></div><span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><Icon size={20} /></span></div></article>)}
                </section>

                <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-amber-50 text-amber-700"><BellRing size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('automations.reminders', 'Appointment reminders')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('automations.remindersCopy', 'Send before a confirmed appointment.')}</p></div></div><input type="checkbox" checked={form.data.reminder_enabled} onChange={(event) => form.setData('reminder_enabled', event.target.checked)} className="mt-2 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" /></div>
                        <div className="mt-6 grid gap-5"><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('automations.sendReminder', 'Send reminder')}<select disabled={!form.data.reminder_enabled} value={form.data.reminder_hours_before} onChange={(event) => form.setData('reminder_hours_before', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-50">{[1, 2, 4, 12, 24, 48, 72].map((hours) => <option key={hours} value={hours}>{hours} {hours === 1 ? t('automations.hour', 'hour') : t('automations.hours', 'hours')} {t('automations.before', 'before')}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('automations.message', 'Message')}<textarea disabled={!form.data.reminder_enabled} rows={7} value={form.data.reminder_message} onChange={(event) => form.setData('reminder_message', event.target.value)} placeholder={t('automations.reminderDefault', reminderDefault)} className="rounded-xl border-slate-300 text-sm leading-6 focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-slate-50" />{form.errors.reminder_message && <span className="text-xs text-rose-600">{form.errors.reminder_message}</span>}</label></div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start justify-between gap-4"><div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><MessageCircleHeart size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('automations.followups', 'Post-appointment follow-ups')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('automations.followupsCopy', 'Check in after a completed appointment.')}</p></div></div><input type="checkbox" checked={form.data.followup_enabled} onChange={(event) => form.setData('followup_enabled', event.target.checked)} className="mt-2 rounded border-slate-300 text-violet-600 focus:ring-violet-500" /></div>
                        <div className="mt-6 grid gap-5"><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('automations.sendFollowup', 'Send follow-up')}<select disabled={!form.data.followup_enabled} value={form.data.followup_hours_after} onChange={(event) => form.setData('followup_hours_after', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm focus:border-violet-500 focus:ring-violet-500 disabled:bg-slate-50">{[0, 1, 2, 4, 24, 48].map((hours) => <option key={hours} value={hours}>{hours === 0 ? t('automations.immediately', 'Immediately after') : `${hours} ${hours === 1 ? t('automations.hour', 'hour') : t('automations.hours', 'hours')} ${t('automations.after', 'after')}`}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('automations.message', 'Message')}<textarea disabled={!form.data.followup_enabled} rows={7} value={form.data.followup_message} onChange={(event) => form.setData('followup_message', event.target.value)} placeholder={t('automations.followupDefault', followupDefault)} className="rounded-xl border-slate-300 text-sm leading-6 focus:border-violet-500 focus:ring-violet-500 disabled:bg-slate-50" />{form.errors.followup_message && <span className="text-xs text-rose-600">{form.errors.followup_message}</span>}</label></div>
                    </section>

                    <div className="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm leading-6 text-blue-800 lg:col-span-2"><strong>{t('automations.variables', 'Available variables:')}</strong> {'{name}'}, {'{service}'}, {'{date}'}, {'{time}'}. {t('automations.variablesCopy', 'Leave a message blank to use the default. Times follow')} {settings.timezone.replaceAll('_', ' ')}.</div>
                    <div className="flex justify-end lg:col-span-2"><button disabled={form.processing} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"><Save size={17} />{form.processing ? t('automations.saving', 'Saving…') : t('automations.save', 'Save automations')}</button></div>
                </form>
            </div>}
        </main>
    </AuthenticatedLayout>;
}
