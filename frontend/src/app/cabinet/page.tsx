'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, type LawyerCabinet, handleUnauthorized } from '@/lib/api';

const STATUS_UI: Record<string, [string, string]> = {
  ok: ['bg-green-50 text-green-700 border-green-300', 'Все гаразд'],
  onboarding: ['bg-blue-50 text-blue-700 border-blue-300', 'Онбординг'],
  warning: ['bg-amber-50 text-amber-700 border-amber-300', 'Потрібна стаття'],
  non_compliant: ['bg-red-50 text-red-700 border-red-300', 'Профіль понижено'],
};

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '/api/v1';

export default function CabinetPage() {
  const router = useRouter();
  const [cab, setCab] = useState<LawyerCabinet | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [uploading, setUploading] = useState(false);

  const load = useCallback(() => {
    api.lawyerCabinet().then(setCab).catch((e) => setError(e.message));
  }, []);

  useEffect(() => {
    if (!localStorage.getItem('access_token')) { router.push('/login?next=/cabinet'); return; }
    load();
  }, [router, load]);

  async function switchMode(mode: 'articles' | 'paid') {
    setError(null);
    try {
      await api.updateLawyerCabinet({ contribution_mode: mode });
      load();
    } catch (e) { setError(e instanceof Error ? e.message : 'Помилка'); }
  }

  async function payLawyerPlan() {
    try {
      const c = await api.checkout({ type: 'subscription', plan_slug: 'lawyer-monthly' });
      sessionStorage.setItem('last_order_id', c.order_id);
      const form = document.createElement('form');
      form.method = 'POST'; form.action = c.checkout_url;
      for (const [n, v] of [['data', c.data], ['signature', c.signature]]) {
        const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; form.appendChild(i);
      }
      document.body.appendChild(form); form.submit();
    } catch (e) { setError(e instanceof Error ? e.message : 'Помилка оплати'); }
  }

  async function uploadDoc(type: string, file: File) {
    setUploading(true); setError(null);
    try {
      const fd = new FormData();
      fd.append('file', file);
      fd.append('type', type);
      const res = await fetch(`${API_BASE}/lawyers/me/documents`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` },
        body: fd,
      });
      if (res.status === 401) { handleUnauthorized(); return; }
      if (!res.ok) throw new Error((await res.json())?.detail ?? 'Помилка завантаження');
      load();
    } catch (e) { setError(e instanceof Error ? e.message : 'Помилка'); }
    finally { setUploading(false); }
  }

  if (!cab) return <div className="mx-auto max-w-4xl px-4 py-14 text-gray-500">{error ?? 'Завантаження…'}</div>;

  const [badgeCls, badgeText] = STATUS_UI[cab.compliance.status] ?? STATUS_UI.ok;

  return (
    <div className="mx-auto max-w-4xl px-4 py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold">Кабінет юриста</h1>
        <div className="flex gap-2">
          <Link href="/cabinet/profile" className="rounded-lg border border-brand-600 px-4 py-2 text-sm font-medium text-brand-700 hover:bg-brand-50">
            Керувати профілем
          </Link>
          <Link href="/cabinet/articles/new" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            + Написати статтю
          </Link>
        </div>
      </div>

      {/* Compliance */}
      <div className={`rounded-2xl border p-6 ${badgeCls}`}>
        <div className="flex items-center justify-between">
          <span className="font-semibold">{badgeText}</span>
          <span className="text-sm">Режим: {cab.contribution_mode === 'articles' ? 'Статті' : 'Платний тариф'}</span>
        </div>
        <p className="mt-2 text-sm">{cab.compliance.hint}</p>
        <div className="mt-4 grid grid-cols-3 gap-3 text-center text-sm">
          <div className="rounded-lg bg-white/60 p-3">
            <div className="text-lg font-bold">{cab.articles_published_count}</div>
            <div className="text-xs">статей опубліковано</div>
          </div>
          <div className="rounded-lg bg-white/60 p-3">
            <div className="text-lg font-bold">{cab.compliance.specializations_covered}/{cab.compliance.specializations_total}</div>
            <div className="text-xs">галузей підтверджено</div>
          </div>
          <div className="rounded-lg bg-white/60 p-3">
            <div className="text-lg font-bold">★ {cab.effective_rating.toFixed(2)}</div>
            <div className="text-xs">рейтинг (+{cab.content_score.toFixed(2)} за статті)</div>
          </div>
        </div>
      </div>

      {/* Статьи или деньги */}
      <div className="grid gap-4 md:grid-cols-2">
        <button onClick={() => switchMode('articles')}
          className={`rounded-2xl border p-6 text-left ${cab.contribution_mode === 'articles' ? 'border-brand-600 ring-1 ring-brand-100' : 'hover:bg-gray-50'}`}>
          <div className="font-semibold">✍️ Писати статті</div>
          <p className="mt-1 text-sm text-gray-600">
            Безкоштовна участь. Стаття у кожну обрану галузь (мін. 2 сторінки), бажано — через день.
            Права на статті переходять порталу. Кожна стаття підвищує рейтинг.
          </p>
        </button>
        <div className={`rounded-2xl border p-6 ${cab.contribution_mode === 'paid' ? 'border-brand-600 ring-1 ring-brand-100' : ''}`}>
          <div className="font-semibold">💳 Тариф «Юрист» — 990 грн/міс</div>
          <p className="mt-1 text-sm text-gray-600">Участь у каталозі без обов&apos;язкових статей + повний PRO-доступ.</p>
          <div className="mt-3 flex gap-2">
            {cab.contribution_mode !== 'paid' && (
              <button onClick={() => switchMode('paid')} className="rounded-lg border px-3 py-1.5 text-sm hover:bg-gray-50">Обрати режим</button>
            )}
            <button onClick={payLawyerPlan} className="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
              Оплатити через LiqPay
            </button>
          </div>
        </div>
      </div>

      {/* Документы верификации */}
      <div className="rounded-2xl border bg-white p-6">
        <h2 className="font-semibold">Документи верифікації {cab.is_verified && <span className="ml-2 text-sm text-green-600">✓ Верифіковано</span>}</h2>
        <p className="mt-1 text-sm text-gray-500">Свідоцтво адвоката (обов&apos;язково) та свідоцтво про освіту (бажано). PDF/JPEG/PNG до 10 МБ.</p>
        <div className="mt-4 grid gap-3 md:grid-cols-2">
          {[['advocate_certificate', 'Свідоцтво адвоката'], ['education_certificate', 'Свідоцтво про освіту']].map(([type, label]) => (
            <label key={type} className="flex cursor-pointer items-center justify-between rounded-lg border border-dashed p-4 text-sm hover:bg-gray-50">
              <span>{label}</span>
              <span className="text-brand-600">{uploading ? '…' : 'Завантажити'}</span>
              <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" disabled={uploading}
                onChange={(e) => e.target.files?.[0] && uploadDoc(type, e.target.files[0])} />
            </label>
          ))}
        </div>
        {cab.documents.length > 0 && (
          <ul className="mt-4 space-y-1 text-sm">
            {cab.documents.map((d) => (
              <li key={d.id} className="flex justify-between rounded bg-gray-50 px-3 py-2">
                <span>{d.file_name}</span>
                <span className={d.status === 'approved' ? 'text-green-600' : d.status === 'rejected' ? 'text-red-600' : 'text-amber-600'}>
                  {d.status === 'approved' ? 'Схвалено' : d.status === 'rejected' ? 'Відхилено' : 'На перевірці'}
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    </div>
  );
}
