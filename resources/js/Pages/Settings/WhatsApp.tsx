import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clipboard, ExternalLink, KeyRound, MessageCircleMore, Save, ShieldCheck, Unplug } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Connection = {
    waba_id: string | null;
    phone_number_id: string;
    display_phone_number: string | null;
    verified_name: string | null;
    status: string;
    connected_at: string | null;
};

type Props = {
    hasWorkspace: boolean;
    connection: Connection | null;
    botEnabled: boolean;
    webhookUrl: string;
    verifyTokenConfigured: boolean;
    appSecretConfigured: boolean;
};

type SharedProps = {
    auth: { user: { id: number; name: string; email: string } };
    flash: { success?: string; error?: string };
};

export default function WhatsApp({ hasWorkspace, connection, botEnabled, webhookUrl, verifyTokenConfigured, appSecretConfigured }: Props) {
    const { t } = useTranslation();
    const { flash } = usePage<SharedProps>().props;
    const [copied, setCopied] = useState(false);
    const form = useForm({
        waba_id: connection?.waba_id ?? '',
        phone_number_id: connection?.phone_number_id ?? '',
        access_token: '',
        bot_enabled: botEnabled,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('whatsapp.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset('access_token'),
        });
    };

    const copyWebhook = async () => {
        await navigator.clipboard.writeText(webhookUrl);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1600);
    };

    const metaReady = verifyTokenConfigured && appSecretConfigured;

    return (
        <AuthenticatedLayout>
            <Head title={t('whatsapp.title', 'WhatsApp setup')} />
            <main className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <span className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('whatsapp.integrations', 'Integrations')}</span>
                        <h1 className="mt-2 text-3xl font-bold tracking-tight text-slate-950">{t('whatsapp.title', 'Connect WhatsApp')}</h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{t('whatsapp.copy', 'Connect an official Meta WhatsApp Business number, verify incoming webhooks, and choose when your booking assistant replies automatically.')}</p>
                    </div>
                    <Link href={route('workspace.edit')} className="text-sm font-semibold text-slate-500 hover:text-slate-900">{t('whatsapp.back', 'Back to workspace setup')}</Link>
                </div>

                {(flash.success || flash.error) && (
                    <div className={`mb-6 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}>
                        <CheckCircle2 size={18} /> {flash.success ?? flash.error}
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-3">
                                <span className="grid h-11 w-11 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><MessageCircleMore size={21} /></span>
                                <div><h2 className="text-lg font-semibold text-slate-900">{t('whatsapp.businessNumber', 'Business number')}</h2><p className="mt-1 text-sm text-slate-500">{t('whatsapp.credentialsCopy', 'Credentials are verified against Meta before they are saved.')}</p></div>
                            </div>
                            {connection?.status === 'connected' && <span className="rounded-full bg-emerald-50 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">{t('common.connected', 'Connected')}</span>}
                        </div>

                        {!hasWorkspace ? (
                            <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-800">{t('whatsapp.workspaceFirst', 'Create your workspace and business hours before connecting WhatsApp.')} <Link href={route('workspace.edit')} className="font-bold underline">{t('whatsapp.createWorkspace', 'Create workspace')}</Link></div>
                        ) : (
                            <form onSubmit={submit} className="mt-7 grid gap-5">
                                {connection && (
                                    <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                                        <div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{t('whatsapp.verifiedBusiness', 'Verified business')}</p><p className="mt-1 font-semibold text-slate-800">{connection.verified_name ?? 'WhatsApp Business'}</p></div>
                                        <div><p className="text-xs font-bold uppercase tracking-wide text-slate-400">{t('whatsapp.phoneNumber', 'Phone number')}</p><p className="mt-1 font-semibold text-slate-800">{connection.display_phone_number ?? connection.phone_number_id}</p></div>
                                    </div>
                                )}

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('whatsapp.phoneNumberId', 'Phone number ID')}<input value={form.data.phone_number_id} onChange={(event) => form.setData('phone_number_id', event.target.value)} inputMode="numeric" className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="123456789012345" />{form.errors.phone_number_id && <span className="text-xs font-medium text-rose-600">{form.errors.phone_number_id}</span>}</label>
                                    <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('whatsapp.wabaId', 'WhatsApp Business Account ID')} <span className="font-normal text-slate-400">{t('common.optional', 'Optional')}</span><input value={form.data.waba_id} onChange={(event) => form.setData('waba_id', event.target.value)} inputMode="numeric" className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="123456789012345" />{form.errors.waba_id && <span className="text-xs font-medium text-rose-600">{form.errors.waba_id}</span>}</label>
                                </div>

                                <label className="grid gap-2 text-sm font-semibold text-slate-700">{t('whatsapp.token', 'Permanent access token')}<input type="password" value={form.data.access_token} onChange={(event) => form.setData('access_token', event.target.value)} autoComplete="new-password" className="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={connection ? t('whatsapp.keepToken', 'Leave blank to keep the saved token') : t('whatsapp.pasteToken', 'Paste the Meta system-user token')} /><span className="text-xs font-normal leading-5 text-slate-400">{t('whatsapp.tokenSecure', 'Stored encrypted and never shown again.')}</span>{form.errors.access_token && <span className="text-xs font-medium text-rose-600">{form.errors.access_token}</span>}</label>

                                <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                                    <input type="checkbox" checked={form.data.bot_enabled} onChange={(event) => form.setData('bot_enabled', event.target.checked)} className="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                    <span><strong className="block text-sm text-slate-800">{t('whatsapp.enableReplies', 'Enable automatic booking replies')}</strong><small className="mt-1 block text-xs leading-5 text-slate-500">{t('whatsapp.enableRepliesCopy', 'When disabled, incoming messages are still recorded but the assistant stays silent.')}</small></span>
                                </label>

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <button type="submit" disabled={form.processing || !metaReady} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"><Save size={17} />{form.processing ? t('whatsapp.verifying', 'Verifying…') : connection ? t('whatsapp.verifySave', 'Verify and save') : t('whatsapp.title', 'Connect WhatsApp')}</button>
                                    {connection && <Link href={route('whatsapp.destroy')} method="delete" as="button" className="inline-flex items-center justify-center gap-2 text-xs font-semibold text-slate-500 hover:text-rose-600"><Unplug size={14} /> {t('whatsapp.disconnect', 'Disconnect number')}</Link>}
                                </div>
                            </form>
                        )}
                    </section>

                    <aside className="grid content-start gap-6">
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-blue-50 text-blue-700"><ShieldCheck size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('whatsapp.webhook', 'Meta webhook')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('whatsapp.webhookCopy', 'Add this callback URL in your Meta app.')}</p></div></div>
                            <div className="mt-5 rounded-xl bg-slate-950 p-4 text-xs text-slate-200"><p className="break-all font-mono leading-5">{webhookUrl}</p></div>
                            <button type="button" onClick={copyWebhook} className="mt-3 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50"><Clipboard size={15} />{copied ? t('whatsapp.copied', 'Copied') : t('whatsapp.copyCallback', 'Copy callback URL')}</button>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-violet-50 text-violet-700"><KeyRound size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('whatsapp.server', 'Server configuration')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('whatsapp.serverCopy', 'Both values must be configured before connecting.')}</p></div></div>
                            <div className="mt-5 grid gap-3">
                                <StatusRow label={t('whatsapp.verifyToken', 'Webhook verify token')} ready={verifyTokenConfigured} />
                                <StatusRow label={t('whatsapp.appSecret', 'Meta app secret')} ready={appSecretConfigured} />
                            </div>
                            {!metaReady && <p className="mt-4 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">{t('whatsapp.envCopy', 'Add WHATSAPP_VERIFY_TOKEN and META_APP_SECRET to the environment, then reload this page.')}</p>}
                            <a href="https://developers.facebook.com/apps/" target="_blank" rel="noreferrer" className="mt-4 inline-flex items-center gap-2 text-xs font-bold text-blue-700 hover:text-blue-900">{t('whatsapp.openMeta', 'Open Meta for Developers')} <ExternalLink size={13} /></a>
                        </section>
                    </aside>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}

function StatusRow({ label, ready }: { label: string; ready: boolean }) {
    const { t } = useTranslation();

    return <div className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700"><span>{label}</span><span className={ready ? 'text-emerald-700' : 'text-amber-700'}>{ready ? t('whatsapp.configured', 'Configured') : t('whatsapp.missing', 'Missing')}</span></div>;
}
