import { useTranslation } from '@/i18n';
import { Languages } from 'lucide-react';

export default function LanguageSwitcher({ compact = false }: { compact?: boolean }) {
    const { language, setLanguage, t } = useTranslation();

    return <div className="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1 shadow-sm" aria-label={t('common.languageSelector', 'Language selector')}>
        {!compact && <Languages size={15} className="mx-2 text-slate-400" />}
        {(['en', 'es'] as const).map((option) => <button key={option} type="button" onClick={() => setLanguage(option)} className={`rounded-lg px-2.5 py-1.5 text-xs font-bold transition ${language === option ? 'bg-emerald-600 text-white' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'}`} aria-pressed={language === option}>{option.toUpperCase()}</button>)}
    </div>;
}
