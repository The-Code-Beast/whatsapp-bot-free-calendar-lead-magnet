import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { useTranslation } from '@/i18n';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const { t } = useTranslation();

    return (
        <AuthenticatedLayout
            header={
                <div><p className="text-xs font-bold uppercase tracking-[0.14em] text-emerald-600">{t('profile.eyebrow', 'Account settings')}</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{t('profile.title', 'Your profile')}</h1><p className="mt-1 text-sm text-slate-500">{t('profile.copy', 'Manage your personal details, password, and account access.')}</p></div>
            }
        >
            <Head title="Profile" />

            <div className="px-4 py-8 sm:px-6 lg:px-8">
                <div className="mx-auto grid max-w-5xl gap-6">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                            className="max-w-2xl"
                        />
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                        <UpdatePasswordForm className="max-w-2xl" />
                    </div>

                    <div className="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm sm:p-8">
                        <DeleteUserForm className="max-w-2xl" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
