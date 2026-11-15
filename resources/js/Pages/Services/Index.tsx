import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Clock3, DollarSign, Pencil, Plus, Save, Scissors, Trash2 } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type Service = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string | null;
    is_active: boolean;
    sort_order: number;
};
type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    hasWorkspace: boolean;
    services: Service[];
    flash: { success?: string; error?: string };
};

const blankService = { name: '', description: '', duration_minutes: 30, price: '', is_active: true, sort_order: '' as number | '' };

export default function ServicesIndex({ hasWorkspace, services }: PageProps) {
    const { t } = useTranslation();
    const { flash } = usePage<PageProps>().props;
    const createForm = useForm(blankService);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        createForm.post(route('services.store'), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('services.catalog', 'Catalog')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('services.title', 'Services')}</h1></div>}>
            <Head title={t('services.title', 'Services')} />
            <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.4fr)_minmax(320px,.6fr)] lg:px-8">
                <section className="grid content-start gap-5">
                    {(flash.success || flash.error) && <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}
                    <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 className="text-lg font-semibold text-slate-900">{t('services.bookable', 'Bookable services')}</h2><p className="mt-1 text-sm text-slate-500">{t('services.copy', 'The assistant uses this catalog to explain options and schedule the right amount of time.')}</p></div><span className="w-fit rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">{services.filter((service) => service.is_active).length} {t('common.active', 'active')}</span></div>

                    {!hasWorkspace ? (
                        <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm"><Scissors className="mx-auto text-slate-300" size={38} /><h3 className="mt-4 font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h3><p className="mt-2 text-sm text-slate-500">{t('services.setupCopy', 'Set your business details before adding services.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></div>
                    ) : services.length ? (
                        <div className="grid gap-4">{services.map((service) => <ServiceCard key={service.id} service={service} />)}</div>
                    ) : (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><Scissors className="mx-auto text-slate-300" size={38} /><h3 className="mt-4 font-semibold text-slate-900">{t('services.none', 'No services yet')}</h3><p className="mt-2 text-sm text-slate-500">{t('services.noneCopy', 'Add the first service customers can book through WhatsApp.')}</p></div>
                    )}
                </section>

                <aside className="lg:sticky lg:top-6 lg:self-start">
                    <form onSubmit={submit} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="flex items-start gap-3"><span className="grid h-10 w-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"><Plus size={19} /></span><div><h2 className="font-semibold text-slate-900">{t('services.add', 'Add a service')}</h2><p className="mt-1 text-xs leading-5 text-slate-500">{t('services.addCopy', 'Create an option the assistant can offer.')}</p></div></div>
                        {!hasWorkspace && <p className="mt-5 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">{t('services.saveWorkspace', 'Save your workspace before adding services.')}</p>}
                        <div className="mt-6 grid gap-4">
                            <Field label={t('services.name', 'Service name')} error={createForm.errors.name}><input value={createForm.data.name} onChange={(event) => createForm.setData('name', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('services.namePlaceholder', 'Initial consultation')} /></Field>
                            <Field label={t('services.description', 'Description')} error={createForm.errors.description}><textarea rows={4} value={createForm.data.description} onChange={(event) => createForm.setData('description', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('services.descriptionPlaceholder', 'What is included and anything the customer should know.')} /></Field>
                            <div className="grid grid-cols-2 gap-3">
                                <Field label={t('services.duration', 'Duration')} error={createForm.errors.duration_minutes}><div className="relative"><Clock3 className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} /><input type="number" min="5" max="480" step="5" value={createForm.data.duration_minutes} onChange={(event) => createForm.setData('duration_minutes', Number(event.target.value))} className="w-full rounded-xl border-slate-300 pl-9 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></div><span className="text-[10px] font-normal text-slate-400">{t('common.minutes', 'minutes')}</span></Field>
                                <Field label={t('services.price', 'Price')} error={createForm.errors.price}><div className="relative"><DollarSign className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} /><input type="number" min="0" step="0.01" value={createForm.data.price} onChange={(event) => createForm.setData('price', event.target.value)} className="w-full rounded-xl border-slate-300 pl-9 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('services.priceOptional', 'Optional')} /></div></Field>
                            </div>
                            <label className="flex items-start gap-3 rounded-xl border border-slate-200 p-3"><input type="checkbox" checked={createForm.data.is_active} onChange={(event) => createForm.setData('is_active', event.target.checked)} className="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" /><span><strong className="block text-xs text-slate-800">{t('services.bookableToggle', 'Active and bookable')}</strong><small className="mt-1 block text-[10px] leading-4 text-slate-500">{t('services.inactiveCopy', 'Inactive services remain saved but are not offered.')}</small></span></label>
                        </div>
                        <button disabled={createForm.processing || !hasWorkspace} className="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"><Plus size={17} />{createForm.processing ? t('services.adding', 'Adding…') : t('services.add', 'Add service')}</button>
                    </form>
                </aside>
            </main>
        </AuthenticatedLayout>
    );
}

function ServiceCard({ service }: { service: Service }) {
    const { language, t } = useTranslation();
    const [editing, setEditing] = useState(false);
    const form = useForm({
        name: service.name,
        description: service.description ?? '',
        duration_minutes: service.duration_minutes,
        price: service.price ?? '',
        is_active: service.is_active,
        sort_order: service.sort_order,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('services.update', service.id), { preserveScroll: true, onSuccess: () => setEditing(false) });
    };

    const remove = () => router.delete(route('services.destroy', service.id), {
        preserveScroll: true,
        onBefore: () => window.confirm(`${t('services.deleteConfirm', 'Delete this service? This cannot be undone.')} (${service.name})`),
    });

    if (editing) {
        return <form onSubmit={submit} className="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm"><div className="grid gap-4 sm:grid-cols-2"><Field label={t('services.name', 'Service name')} error={form.errors.name}><input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></Field><div className="grid grid-cols-2 gap-3"><Field label={t('common.minutes', 'Minutes')} error={form.errors.duration_minutes}><input type="number" min="5" max="480" step="5" value={form.data.duration_minutes} onChange={(event) => form.setData('duration_minutes', Number(event.target.value))} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></Field><Field label={t('services.price', 'Price')} error={form.errors.price}><input type="number" min="0" step="0.01" value={form.data.price} onChange={(event) => form.setData('price', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></Field></div><div className="sm:col-span-2"><Field label={t('services.description', 'Description')} error={form.errors.description}><textarea rows={3} value={form.data.description} onChange={(event) => form.setData('description', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></Field></div></div><div className="mt-4 flex flex-wrap items-center justify-between gap-3"><label className="flex items-center gap-2 text-xs font-semibold text-slate-700"><input type="checkbox" checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />{t('services.bookableToggle', 'Active and bookable')}</label><div className="flex gap-2"><button type="button" onClick={() => setEditing(false)} className="rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">{t('common.cancel', 'Cancel')}</button><button disabled={form.processing} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700"><Save size={14} />{t('common.save', 'Save')}</button></div></div></form>;
    }

    return <article className={`rounded-2xl border bg-white p-5 shadow-sm ${service.is_active ? 'border-slate-200' : 'border-dashed border-slate-300 opacity-75'}`}><div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div className="flex min-w-0 gap-4"><span className={`grid h-11 w-11 shrink-0 place-items-center rounded-xl ${service.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}><Scissors size={20} /></span><div className="min-w-0"><div className="flex flex-wrap items-center gap-2"><h3 className="font-semibold text-slate-900">{service.name}</h3><span className={`rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide ${service.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}>{service.is_active ? t('common.active', 'Active') : t('common.inactive', 'Inactive')}</span></div><div className="mt-2 flex flex-wrap gap-3 text-xs font-medium text-slate-500"><span className="inline-flex items-center gap-1.5"><Clock3 size={14} />{service.duration_minutes} {t('common.minutes', 'minutes')}</span><span className="inline-flex items-center gap-1.5"><DollarSign size={14} />{service.price !== null ? Number(service.price).toLocaleString(language === 'es' ? 'es-US' : 'en-US', { style: 'currency', currency: 'USD' }) : t('services.priceMissing', 'Price not listed')}</span></div>{service.description && <p className="mt-3 text-sm leading-6 text-slate-500">{service.description}</p>}</div></div><div className="flex shrink-0 gap-2"><button onClick={() => setEditing(true)} className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-900" aria-label={`${t('services.edit', 'Edit')} ${service.name}`}><Pencil size={15} /></button><button onClick={remove} className="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-400 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600" aria-label={`${t('services.delete', 'Delete')} ${service.name}`}><Trash2 size={15} /></button></div></div></article>;
}

function Field({ label, error, children }: { label: string; error?: string; children: ReactNode }) {
    return <label className="grid gap-2 text-sm font-semibold text-slate-700">{label}{children}{error && <span className="text-xs font-medium leading-5 text-rose-600">{error}</span>}</label>;
}
