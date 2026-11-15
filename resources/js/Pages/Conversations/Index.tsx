import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useTranslation } from '@/i18n';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Bot, CheckCircle2, CirclePause, MessageCircleMore, Phone, Search, Send, UserRound } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type LatestMessage = { body: string | null; direction: string; sent_at: string | null };
type ConversationSummary = {
    id: number;
    customer_name: string | null;
    customer_wa_id: string;
    status: string;
    booking_stage: string;
    human_takeover: boolean;
    last_message_at: string | null;
    unread_count: number;
    latest_message: LatestMessage | null;
};
type Message = {
    id: number;
    direction: string;
    sender_type: string;
    body: string | null;
    status: string;
    sent_at: string | null;
    read_at: string | null;
};
type SelectedConversation = Omit<ConversationSummary, 'latest_message'> & {
    booking_data: Record<string, unknown> | null;
    connection_ready: boolean;
    messages: Message[];
};
type PaginationLink = { url: string | null; label: string; active: boolean };
type PageProps = {
    auth: { user: { id: number; name: string; email: string } };
    conversations: { data: ConversationSummary[]; links: PaginationLink[]; total: number } | null;
    selectedConversation: SelectedConversation | null;
    hasWorkspace: boolean;
    search: string;
    flash: { success?: string; error?: string };
};

const paginationLabel = (label: string) => label.replace('&laquo;', '‹').replace('&raquo;', '›');
const formatTime = (value: string | null, locale: string) => value ? new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(value)) : '';

export default function ConversationsIndex({ conversations, selectedConversation, hasWorkspace, search }: PageProps) {
    const { language, t } = useTranslation();
    const locale = language === 'es' ? 'es-US' : 'en-US';
    const { flash } = usePage<PageProps>().props;
    const [query, setQuery] = useState(search);
    const replyForm = useForm({ body: '' });

    useEffect(() => {
        if (selectedConversation && selectedConversation.unread_count > 0) {
            router.post(route('conversations.read', selectedConversation.id), {}, {
                preserveScroll: true,
                preserveState: true,
                only: ['conversations', 'selectedConversation'],
            });
        }
    }, [selectedConversation?.id, selectedConversation?.unread_count]);

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('conversations.index'), query ? { search: query } : {}, { preserveState: true, replace: true });
    };

    const submitReply = (event: FormEvent) => {
        event.preventDefault();
        if (!selectedConversation) return;
        replyForm.post(route('conversations.reply', selectedConversation.id), {
            preserveScroll: true,
            onSuccess: () => replyForm.reset(),
        });
    };

    return (
        <AuthenticatedLayout header={<div><p className="text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">{t('conversations.inbox', 'Inbox')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{t('conversations.title', 'Conversations')}</h1></div>}>
            <Head title={t('conversations.title', 'Conversations')} />
            <main className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                {(flash.success || flash.error) && <div className={`mb-5 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium ${flash.success ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'}`}><CheckCircle2 size={18} />{flash.success ?? flash.error}</div>}

                {!hasWorkspace ? (
                    <section className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm"><MessageCircleMore className="mx-auto text-slate-300" size={40} /><h2 className="mt-4 text-lg font-semibold text-slate-900">{t('common.createWorkspace', 'Create your workspace first')}</h2><p className="mt-2 text-sm text-slate-500">{t('conversations.setupCopy', 'Your WhatsApp conversations will appear here after setup.')}</p><Link href={route('workspace.edit')} className="mt-5 inline-flex rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white">{t('common.openSetup', 'Open setup')}</Link></section>
                ) : (
                    <div className="grid min-h-[680px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:grid-cols-[380px_minmax(0,1fr)]">
                        <section className={`${selectedConversation ? 'hidden lg:flex' : 'flex'} min-w-0 flex-col border-r border-slate-200`}>
                            <div className="border-b border-slate-200 p-4">
                                <div className="flex items-end justify-between gap-3"><div><h2 className="font-semibold text-slate-900">{t('conversations.chats', 'Customer chats')}</h2><p className="mt-1 text-xs text-slate-500">{conversations?.total ?? 0} {t('conversations.count', 'conversations')}</p></div><span className="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-emerald-700">{t('conversations.live', 'Live inbox')}</span></div>
                                <form onSubmit={submitSearch} className="relative mt-4"><Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} /><input value={query} onChange={(event) => setQuery(event.target.value)} className="w-full rounded-xl border-slate-300 py-2.5 pl-9 pr-3 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('conversations.search', 'Search name or number')} /></form>
                            </div>

                            <div className="flex-1 divide-y divide-slate-100 overflow-y-auto">
                                {conversations?.data.length ? conversations.data.map((conversation) => (
                                    <Link key={conversation.id} href={route('conversations.show', conversation.id)} className={`grid grid-cols-[42px_minmax(0,1fr)_auto] gap-3 p-4 transition hover:bg-slate-50 ${selectedConversation?.id === conversation.id ? 'bg-emerald-50/70' : ''}`}>
                                        <span className="grid h-10 w-10 place-items-center rounded-full bg-slate-100 font-bold text-slate-600">{(conversation.customer_name ?? conversation.customer_wa_id).slice(0, 1).toUpperCase()}</span>
                                        <span className="min-w-0"><span className="flex items-center gap-2"><strong className="truncate text-sm text-slate-900">{conversation.customer_name ?? conversation.customer_wa_id}</strong>{conversation.human_takeover && <UserRound size={13} className="shrink-0 text-violet-600" />}</span><small className="mt-1 block truncate text-xs text-slate-500">{conversation.latest_message?.direction === 'outbound' ? `${t('conversations.you', 'You:')} ` : ''}{conversation.latest_message?.body ?? t('conversations.noMessagesYet', 'No messages yet')}</small></span>
                                        <span className="grid justify-items-end gap-2"><small className="whitespace-nowrap text-[10px] text-slate-400">{formatTime(conversation.last_message_at, locale)}</small>{conversation.unread_count > 0 && <b className="grid h-5 min-w-5 place-items-center rounded-full bg-emerald-600 px-1 text-[10px] text-white">{conversation.unread_count}</b>}</span>
                                    </Link>
                                )) : <div className="grid place-items-center px-6 py-16 text-center"><div><MessageCircleMore className="mx-auto text-slate-300" size={32} /><h3 className="mt-3 text-sm font-semibold text-slate-800">{t('conversations.none', 'No conversations found')}</h3><p className="mt-1 text-xs leading-5 text-slate-500">{t('conversations.noneCopy', 'Incoming WhatsApp messages will appear here.')}</p></div></div>}
                            </div>

                            {conversations && conversations.links.length > 3 && <nav className="flex justify-center gap-1 border-t border-slate-200 p-3">{conversations.links.map((link, index) => link.url ? <Link key={index} href={link.url} preserveState className={`rounded-lg px-2.5 py-1.5 text-xs ${link.active ? 'bg-slate-900 font-bold text-white' : 'text-slate-500 hover:bg-slate-100'}`}>{paginationLabel(link.label)}</Link> : <span key={index} className="px-2.5 py-1.5 text-xs text-slate-300">{paginationLabel(link.label)}</span>)}</nav>}
                        </section>

                        {selectedConversation ? (
                            <section className="flex min-w-0 flex-col bg-slate-50/60">
                                <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:px-5">
                                    <div className="flex min-w-0 items-center gap-3"><Link href={route('conversations.index')} className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"><ArrowLeft size={18} /></Link><span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-50 font-bold text-emerald-700">{(selectedConversation.customer_name ?? selectedConversation.customer_wa_id).slice(0, 1).toUpperCase()}</span><div className="min-w-0"><h2 className="truncate font-semibold text-slate-900">{selectedConversation.customer_name ?? t('conversations.whatsappCustomer', 'WhatsApp customer')}</h2><p className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500"><Phone size={12} />{selectedConversation.customer_wa_id}</p></div></div>
                                    {selectedConversation.human_takeover ? <Link href={route('conversations.takeover.destroy', selectedConversation.id)} method="delete" as="button" preserveScroll className="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800"><Bot size={14} />{t('conversations.returnBot', 'Return to bot')}</Link> : <Link href={route('conversations.takeover.store', selectedConversation.id)} method="post" as="button" preserveScroll className="inline-flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2 text-xs font-bold text-violet-800"><CirclePause size={14} />{t('conversations.takeOver', 'Take over')}</Link>}
                                </header>

                                <div className="flex flex-wrap gap-2 border-b border-slate-200 bg-white px-5 py-2.5 text-[10px] font-bold uppercase tracking-wide"><span className="rounded-full bg-slate-100 px-2.5 py-1 text-slate-600">{selectedConversation.status}</span><span className="rounded-full bg-blue-50 px-2.5 py-1 text-blue-700">{selectedConversation.booking_stage.replaceAll('_', ' ')}</span>{selectedConversation.human_takeover && <span className="rounded-full bg-violet-50 px-2.5 py-1 text-violet-700">{t('conversations.humanTakeover', 'Human takeover')}</span>}</div>

                                <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4 sm:p-6">
                                    {selectedConversation.messages.length ? selectedConversation.messages.map((message) => <div key={message.id} className={`flex ${message.direction === 'outbound' ? 'justify-end' : 'justify-start'}`}><div className={`max-w-[82%] rounded-2xl px-4 py-3 shadow-sm ${message.direction === 'outbound' ? 'rounded-br-md bg-emerald-600 text-white' : 'rounded-bl-md border border-slate-200 bg-white text-slate-800'}`}><p className="whitespace-pre-wrap break-words text-sm leading-6">{message.body}</p><div className={`mt-1.5 flex items-center justify-end gap-2 text-[10px] ${message.direction === 'outbound' ? 'text-emerald-100' : 'text-slate-400'}`}><span>{message.sender_type === 'human' ? t('conversations.team', 'Team') : message.sender_type === 'bot' ? t('conversations.bot', 'Bot') : t('conversations.customer', 'Customer')}</span><span>{formatTime(message.sent_at, locale)}</span>{message.direction === 'outbound' && <span>{message.status}</span>}</div></div></div>) : <div className="grid flex-1 place-items-center text-center"><div><MessageCircleMore className="mx-auto text-slate-300" size={34} /><p className="mt-3 text-sm text-slate-500">{t('conversations.noMessages', 'No messages in this conversation.')}</p></div></div>}
                                </div>

                                <form onSubmit={submitReply} className="border-t border-slate-200 bg-white p-4 sm:p-5">
                                    {!selectedConversation.connection_ready && <p className="mb-3 rounded-xl bg-amber-50 p-3 text-xs leading-5 text-amber-800">{t('conversations.reconnect', 'Reconnect WhatsApp before sending manual replies.')}</p>}
                                    <div className="flex items-end gap-3"><label className="sr-only" htmlFor="reply-body">{t('conversations.reply', 'Reply')}</label><textarea id="reply-body" rows={2} value={replyForm.data.body} onChange={(event) => replyForm.setData('body', event.target.value)} className="min-h-[50px] flex-1 resize-none rounded-xl border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder={t('conversations.replyPlaceholder', 'Write a reply…')} /><button disabled={replyForm.processing || !selectedConversation.connection_ready || !replyForm.data.body.trim()} className="grid h-[50px] w-[50px] shrink-0 place-items-center rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"><Send size={18} /></button></div>
                                    {replyForm.errors.body && <p className="mt-2 text-xs font-medium text-rose-600">{replyForm.errors.body}</p>}
                                    <p className="mt-2 text-[10px] leading-4 text-slate-400">{t('conversations.manualCopy', 'Sending a manual reply automatically pauses the bot for this customer.')}</p>
                                </form>
                            </section>
                        ) : <section className="hidden place-items-center bg-slate-50/60 text-center lg:grid"><div><span className="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-white text-slate-300 shadow-sm"><MessageCircleMore size={30} /></span><h2 className="mt-4 font-semibold text-slate-800">{t('conversations.select', 'Select a conversation')}</h2><p className="mt-2 text-sm text-slate-500">{t('conversations.selectCopy', 'Open a customer chat to view messages or take over.')}</p></div></section>}
                    </div>
                )}
            </main>
        </AuthenticatedLayout>
    );
}
