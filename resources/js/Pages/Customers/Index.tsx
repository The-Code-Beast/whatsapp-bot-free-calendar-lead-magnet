import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarCheck2, CheckCircle2, Mail, MessageCircleMore, Phone, Save, Search, UserRound, UsersRound } from 'lucide-react';
import { FormEvent } from 'react';

type Customer = {
    id: number;
    name: string | null;
    phone: string;
    email: string | null;
    status: string;
    source: string;
    appointments_count: number;
    conversations_count: number;
    last_contact_at: string | null;
};
type SelectedCustomer = Omit<Customer, 'appointments_count' | 'conversations_count'> & {
    notes: string | null;
    appointments: { public_id: string; service: string | null; starts_at: string; status: string }[];
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    hasWorkspace: boolean;
    customers: { data: Customer[]; links: PaginationLink[]; total: number } | null;
    selectedCustomer: SelectedCustomer | null;
    filters: { search: string; status: string };
    statusCounts: Record<string, number>;
    flash: { success?: string; error?: string };
};

const statuses = ['new', 'lead', 'qualified', 'booked', 'completed', 'lost'];
const paginationLabel = (label: string) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
const formatDate = (value: string | null, locale: string, empty: string) => value ? new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : empty;
const statusColor = (status: string) => ({
    new: 'bg-blue-50 text-blue-700', lead: 'bg-amber-50 text-amber-700', qualified: 'bg-violet-50 text-violet-700', booked: 'bg-emerald-50 text-emerald-700', completed: 'bg-slate-100 text-slate-700', lost: 'bg-rose-50 text-rose-700',
}[status] ?? 'bg-slate-100 text-slate-600');

function CustomerDetail({ customer }: { customer: SelectedCustomer }) {
    const { language, t } = useTranslation();
    const localizedStatus = (status: string) => t(`customers.${status}`, status.charAt(0).toUpperCase() + status.slice(1));
    const form = useForm({ name: customer.name ?? '', email: customer.email ?? '', status: customer.status, notes: customer.notes ?? '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(route('customers.update', customer.id), { preserveScroll: true });
    };

    return <div className="grid gap-6">
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="flex items-start gap-4"><span className="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-emerald-50 text-lg font-bold text-emerald-700">{(customer.name ?? customer.phone).slice(0, 1).toUpperCase()}</span><div className="min-w-0"><h2 className="truncate text-lg font-semibold text-slate-900">{customer.name ?? t('customers.unnamed', 'Unnamed customer')}</h2><div className="mt-2 flex flex-wrap gap-3 text-xs text-slate-500"><span className="inline-flex items-center gap-1.5"><Phone size={13} />{customer.phone}</span>{customer.email && <span className="inline-flex items-center gap-1.5"><Mail size={13} />{customer.email}</span>}</div><p className="mt-2 text-xs text-slate-400">{t('customers.lastContact', 'Last contact:')} {formatDate(customer.last_contact_at, language === 'es' ? 'es-US' : 'en-US', t('customers.noActivity', 'No activity yet'))}</p></div></div>
            <form onSubmit={submit} className="mt-6 grid gap-4"><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('customers.name', 'Name')}<input value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('customers.email', 'Email')}<input type="email" value={form.data.email} onChange={(event) => form.setData('email', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" />{form.errors.email && <span className="text-xs text-rose-600">{form.errors.email}</span>}</label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('customers.status', 'Lead status')}<select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">{statuses.map((status) => <option key={status} value={status}>{localizedStatus(status)}</option>)}</select></label><label className="grid gap-2 text-sm font-semibold text-slate-700">{t('customers.notes', 'Internal notes')}<textarea rows={5} value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} placeholder={t('customers.notesPlaceholder', 'Preferences, follow-up context, or anything the team should know.')} className="rounded-xl border-slate-300 text-sm leading-6 focus:border-emerald-500 focus:ring-emerald-500" />{form.errors.notes && <span className="text-xs text-rose-600">{form.errors.notes}</span>}</label><button disabled={form.processing} className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60"><Save size={16} /> {t('customers.save', 'Save customer')}</button></form>
        </section>
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"><h3 className="font-semibold text-slate-900">{t('customers.history', 'Booking history')}</h3>{customer.appointments.length ? <div className="mt-4 grid gap-3">{customer.appointments.map((appointment) => <article key={appointment.public_id} className="flex items-start gap-3 rounded-xl bg-slate-50 p-4"><CalendarCheck2 size={17} className="mt-0.5 shrink-0 text-emerald-600" /><div><p className="text-sm font-semibold text-slate-800">{appointment.service ?? t('appointments.appointment', 'Appointment')}</p><p className="mt-1 text-xs text-slate-500">{formatDate(appointment.starts_at, language === 'es' ? 'es-US' : 'en-US', '')} · {t(`appointments.${appointment.status}`, appointment.status)}</p></div></article>)}</div> : <p className="mt-4 text-sm text-slate-500">{t('customers.noAppointments', 'No appointments yet.')}</p>}</section>
    </div>;
}

export default function CustomersIndex({ hasWorkspace, customers, selectedCustomer, filters, statusCounts }: PageProps) {
    const { language, t } = useTranslation();
    const localizedStatus = (status: string) => t(`customers.${status}`, status.charAt(0).toUpperCase() + status.slice(1));
    const { flash } = usePage<PageProps>().props;
    const search = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const value = new FormData(event.currentTarget).get('search')?.toString() ?? '';
        router.get(route('customers.index'), { search: value, status: filters.status }, { preserveState: true, replace: true });
    };
    const filterStatus = (status: string) => router.get(route('customers.index'), { search: filters.search, status }, { preserveState: true, replace: true });

    return <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('customers.crm', 'CRM')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('customers.title', 'Customers')}</h1></div>}>
        <Head title={t('customers.title', 'Customers')} />
        <main className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-[minmax(0,1.15fr)_minmax(340px,.85fr)] lg:px-8">
            {!hasWorkspace ? <section className="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm lg:col-span-2"><UsersRound className="mx-auto text-slate-300" size={38} /><h2 className="mt-4 font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h2><p className="mt-2 text-sm text-slate-500">{t('customers.setupCopy', 'Customer records are organized inside your workspace.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></section> : <>
                <section className="grid content-start gap-5">
                    {(flash.success || flash.error) && <div className={`flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}
                    <div className="flex flex-col gap-3 sm:flex-row"><form onSubmit={search} className="relative flex-1"><Search size={16} className="absolute left-3 top-3 text-slate-400" /><input name="search" defaultValue={filters.search} placeholder={t('customers.search', 'Search name, phone, or email')} className="w-full rounded-xl border-slate-300 py-2.5 pl-9 text-sm focus:border-emerald-500 focus:ring-emerald-500" /></form><select value={filters.status} onChange={(event) => filterStatus(event.target.value)} className="rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"><option value="">{t('customers.allStatuses', 'All statuses')}</option>{statuses.map((status) => <option key={status} value={status}>{localizedStatus(status)} ({statusCounts[status] ?? 0})</option>)}</select></div>
                    <div className="flex items-center justify-between gap-4"><p className="text-sm text-slate-500">{customers?.total ?? 0} {t('customers.records', 'customer records')}</p><span className="text-xs text-slate-400">{t('customers.synced', 'Synced from bookings and WhatsApp')}</span></div>
                    {customers?.data.length ? <div className="grid gap-3">{customers.data.map((customer) => <Link key={customer.id} href={route('customers.index', { customer: customer.id, search: filters.search, status: filters.status })} preserveState className={`rounded-2xl border bg-white p-5 shadow-sm transition hover:border-emerald-300 ${selectedCustomer?.id === customer.id ? 'border-emerald-400 ring-2 ring-emerald-100' : 'border-slate-200'}`}><div className="flex items-start justify-between gap-4"><div className="flex min-w-0 gap-3"><span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-slate-100 font-bold text-slate-600">{(customer.name ?? customer.phone).slice(0, 1).toUpperCase()}</span><div className="min-w-0"><h3 className="truncate text-sm font-semibold text-slate-900">{customer.name ?? t('customers.unnamed', 'Unnamed customer')}</h3><p className="mt-1 text-xs text-slate-500">{customer.phone}{customer.email ? ` · ${customer.email}` : ''}</p></div></div><span className={`rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${statusColor(customer.status)}`}>{localizedStatus(customer.status)}</span></div><div className="mt-4 flex flex-wrap gap-4 border-t border-slate-100 pt-3 text-xs text-slate-500"><span className="inline-flex items-center gap-1.5"><CalendarCheck2 size={13} />{customer.appointments_count} {t('customers.bookings', 'bookings')}</span><span className="inline-flex items-center gap-1.5"><MessageCircleMore size={13} />{customer.conversations_count} {t('customers.conversations', 'conversations')}</span><span className="ml-auto">{formatDate(customer.last_contact_at, language === 'es' ? 'es-US' : 'en-US', t('customers.noActivity', 'No activity yet'))}</span></div></Link>)}</div> : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"><UserRound className="mx-auto text-slate-300" size={36} /><h3 className="mt-4 font-semibold text-slate-900">{t('customers.none', 'No customers found')}</h3><p className="mt-2 text-sm text-slate-500">{t('customers.noneCopy', 'Customer records appear automatically after a WhatsApp message or booking.')}</p></div>}
                    {customers && customers.links.length > 3 && <nav className="flex justify-center gap-1">{customers.links.map((link, index) => link.url ? <Link key={index} href={link.url} preserveState className={`rounded-lg px-3 py-2 text-sm ${link.active ? 'bg-slate-900 font-bold text-white' : 'text-slate-500 hover:bg-white'}`}>{paginationLabel(link.label)}</Link> : <span key={index} className="px-3 py-2 text-sm text-slate-300">{paginationLabel(link.label)}</span>)}</nav>}
                </section>
                <aside className="lg:sticky lg:top-6 lg:self-start">{selectedCustomer ? <CustomerDetail key={selectedCustomer.id} customer={selectedCustomer} /> : <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><UsersRound className="mx-auto text-slate-300" size={34} /><h3 className="mt-4 font-semibold text-slate-900">{t('customers.select', 'Select a customer')}</h3><p className="mt-2 text-sm leading-6 text-slate-500">{t('customers.selectCopy', 'View booking history, update lead status, and save internal notes.')}</p></div>}</aside>
            </>}
        </main>
    </AuthenticatedLayout>;
}
