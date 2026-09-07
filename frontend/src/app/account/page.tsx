'use client';

import { useCallback, useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, handleUnauthorized, type Account, type AccountQuestion, type AccountPayment } from '@/lib/api';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '/api/v1';

const STATUS_Q: Record<string, [string, string]> = {
  open: ['bg-blue-50 text-blue-700', 'Відкрите'],
  answered: ['bg-emerald-50 text-emerald-700', 'Є відповіді'],
  closed: ['bg-slate-100 text-slate-600', 'Закрите'],
  moderation: ['bg-amber-50 text-amber-700', 'На модерації'],
  archived: ['bg-slate-100 text-slate-500', 'Архів'],
};

const STATUS_P: Record<string, [string, string]> = {
  succeeded: ['bg-emerald-50 text-emerald-700', 'Оплачено'],
  pending: ['bg-amber-50 text-amber-700', 'Очікує'],
  failed: ['bg-red-50 text-red-700', 'Помилка'],
  refunded: ['bg-slate-100 text-slate-600', 'Повернено'],
};

type Tab = 'profile' | 'questions' | 'payments';

export default function AccountPage() {
  const router = useRouter();
  const [tab, setTab] = useState<Tab>('profile');
  const [acc, setAcc] = useState<Account | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    api.account().then(setAcc).catch((e) => setError(e.message));
  }, []);

  useEffect(() => {
    if (!localStorage.getItem('access_token')) { router.push('/login?next=/account'); return; }
    load();
  }, [router, load]);

  if (error) return <div className="mx-auto max-w-3xl px-4 py-16 text-center text-red-600">{error}</div>;
  if (!acc) return <div className="mx-auto max-w-3xl px-4 py-16 text-center text-gray-400">Завантаження…</div>;

  return (
    <div className="mx-auto max-w-4xl px-4 py-10">
      {/* Шапка профиля */}
      <div className="flex flex-col items-start gap-5 rounded-2xl border bg-white p-6 sm:flex-row sm:items-center">
        <AvatarBlock acc={acc} onUploaded={load} setError={setError} />
        <div className="min-w-0 flex-1">
          <h1 className="text-2xl font-bold text-gray-900">{acc.full_name}</h1>
          <p className="text-sm text-gray-500">{acc.email}</p>
          <p className="mt-1 text-xs text-gray-400">
            {acc.role === 'client' ? 'Клієнт' : acc.role} · на платформі з {new Date(acc.member_since).toLocaleDateString('uk-UA')}
          </p>
        </div>
        <div className="flex gap-6 text-center">
          <div><div className="text-xl font-bold text-brand-700">{acc.stats.questions}</div><div className="text-xs text-gray-400">питань</div></div>
          <div><div className="text-xl font-bold text-brand-700">{acc.stats.payments}</div><div className="text-xs text-gray-400">платежів</div></div>
        </div>
      </div>

      {/* Вкладки */}
      <div className="mt-6 flex gap-1 border-b">
        {([['profile', 'Профіль'], ['questions', 'Мої питання'], ['payments', 'Платежі']] as [Tab, string][]).map(([id, label]) => (
          <button key={id} onClick={() => setTab(id)}
            className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition ${tab === id ? 'border-brand-600 text-brand-700' : 'border-transparent text-gray-500 hover:text-gray-700'}`}>
            {label}
          </button>
        ))}
      </div>

      <div className="mt-6">
        {tab === 'profile' && <ProfileTab acc={acc} onSaved={load} />}
        {tab === 'questions' && <QuestionsTab />}
        {tab === 'payments' && <PaymentsTab />}
      </div>
    </div>
  );
}

function AvatarBlock({ acc, onUploaded, setError }: { acc: Account; onUploaded: () => void; setError: (s: string) => void }) {
  const [uploading, setUploading] = useState(false);

  async function upload(file: File) {
    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch(`${API_BASE}/account/avatar`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${localStorage.getItem('access_token')}` },
        body: fd,
      });
      if (res.status === 401) { handleUnauthorized(); return; }
      if (!res.ok) throw new Error((await res.json())?.detail ?? 'Помилка завантаження');
      onUploaded();
    } catch (e) { setError(e instanceof Error ? e.message : 'Помилка'); }
    finally { setUploading(false); }
  }

  return (
    <label className="group relative cursor-pointer">
      <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-2xl font-semibold text-brand-700">
        {acc.avatar_url ? <img src={acc.avatar_url} alt="" className="h-full w-full object-cover" /> : acc.full_name.slice(0, 1)}
      </div>
      <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/40 text-[10px] text-white opacity-0 transition group-hover:opacity-100">
        {uploading ? '…' : 'Змінити'}
      </div>
      <input type="file" accept="image/*" className="hidden" disabled={uploading}
        onChange={(e) => e.target.files?.[0] && upload(e.target.files[0])} />
    </label>
  );
}

function ProfileTab({ acc, onSaved }: { acc: Account; onSaved: () => void }) {
  const [form, setForm] = useState({ full_name: acc.full_name, phone: acc.phone ?? '', locale: acc.locale });
  const [msg, setMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [saving, setSaving] = useState(false);

  async function save() {
    setSaving(true); setMsg(null);
    try {
      await api.updateAccount({ full_name: form.full_name, phone: form.phone || null, locale: form.locale });
      setMsg({ ok: true, text: 'Збережено' });
      onSaved();
    } catch (e) { setMsg({ ok: false, text: e instanceof Error ? e.message : 'Помилка' }); }
    finally { setSaving(false); }
  }

  return (
    <div className="rounded-2xl border bg-white p-6">
      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block">
          <span className="mb-1.5 block text-sm font-medium text-gray-700">Повне ім&apos;я</span>
          <input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })}
            className="w-full rounded-lg border p-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
        </label>
        <label className="block">
          <span className="mb-1.5 block text-sm font-medium text-gray-700">Email</span>
          <input value={acc.email} disabled className="w-full rounded-lg border bg-gray-50 p-2.5 text-sm text-gray-400" />
          <span className="mt-1 block text-xs text-gray-400">Email змінюється через підтримку (потребує верифікації)</span>
        </label>
        <label className="block">
          <span className="mb-1.5 block text-sm font-medium text-gray-700">Телефон</span>
          <input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+380…"
            className="w-full rounded-lg border p-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20" />
        </label>
        <label className="block">
          <span className="mb-1.5 block text-sm font-medium text-gray-700">Мова інтерфейсу</span>
          <select value={form.locale} onChange={(e) => setForm({ ...form, locale: e.target.value })}
            className="w-full rounded-lg border p-2.5 text-sm focus:border-brand-500 focus:outline-none">
            <option value="uk">Українська</option>
            <option value="en">English</option>
          </select>
        </label>
      </div>
      <div className="mt-5 flex items-center gap-3">
        <button onClick={save} disabled={saving}
          className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {saving ? 'Збереження…' : 'Зберегти'}
        </button>
        {msg && <span className={`text-sm ${msg.ok ? 'text-emerald-600' : 'text-red-600'}`}>{msg.text}</span>}
      </div>
    </div>
  );
}

function QuestionsTab() {
  const [items, setItems] = useState<AccountQuestion[] | null>(null);

  useEffect(() => { api.accountQuestions().then((r) => setItems(r.data)).catch(() => setItems([])); }, []);

  if (!items) return <div className="py-10 text-center text-gray-400">Завантаження…</div>;
  if (items.length === 0) return (
    <div className="rounded-2xl border border-dashed bg-white py-14 text-center">
      <p className="font-medium text-gray-600">Ви ще не ставили питань</p>
      <p className="mt-1 text-sm text-gray-400">Поставте питання — юристи відповідають безкоштовно.</p>
      <Link href="/ask" className="mt-4 inline-block rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">Поставити питання</Link>
    </div>
  );

  return (
    <div className="space-y-3">
      {items.map((q) => {
        const [cls, label] = STATUS_Q[q.status] ?? ['bg-slate-100 text-slate-600', q.status];
        return (
          <Link key={q.id} href={`/qa/${q.id}`} className="block rounded-xl border bg-white p-4 hover:shadow-sm">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="font-medium text-gray-900">{q.title}</div>
                <div className="mt-1 text-xs text-gray-400">{q.category} · переглядів: {q.views_count} · {new Date(q.created_at).toLocaleDateString('uk-UA')}</div>
              </div>
              <div className="flex shrink-0 flex-col items-end gap-1.5">
                <span className={`rounded-md px-2 py-0.5 text-xs font-medium ${cls}`}>{label}</span>
                <span className="text-xs text-gray-500">
                  {q.answers_count} відп.{q.has_accepted && <span className="ml-1 text-emerald-600">✓</span>}
                </span>
              </div>
            </div>
          </Link>
        );
      })}
    </div>
  );
}

function PaymentsTab() {
  const [items, setItems] = useState<AccountPayment[] | null>(null);

  useEffect(() => { api.accountPayments().then((r) => setItems(r.data)).catch(() => setItems([])); }, []);

  if (!items) return <div className="py-10 text-center text-gray-400">Завантаження…</div>;
  if (items.length === 0) return (
    <div className="rounded-2xl border border-dashed bg-white py-14 text-center">
      <p className="font-medium text-gray-600">Платежів поки немає</p>
      <p className="mt-1 text-sm text-gray-400">Тут з&apos;являться оплачені консультації та підписки.</p>
    </div>
  );

  return (
    <div className="overflow-hidden rounded-2xl border bg-white">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b bg-gray-50/60 text-left text-xs font-medium uppercase tracking-wide text-gray-400">
            <th className="px-4 py-3">Тип</th><th className="px-4 py-3">Сума</th><th className="px-4 py-3">Статус</th><th className="px-4 py-3">Дата</th>
          </tr>
        </thead>
        <tbody>
          {items.map((p) => {
            const [cls, label] = STATUS_P[p.status] ?? ['bg-slate-100 text-slate-600', p.status];
            return (
              <tr key={p.id} className="border-b border-gray-50 last:border-0 hover:bg-gray-50/40">
                <td className="px-4 py-3 font-medium text-gray-800">{p.type_label}</td>
                <td className="px-4 py-3 tabular-nums text-gray-700">{Number(p.amount).toLocaleString('uk-UA')} {p.currency}</td>
                <td className="px-4 py-3"><span className={`rounded-md px-2 py-0.5 text-xs font-medium ${cls}`}>{label}</span></td>
                <td className="px-4 py-3 text-gray-500">{new Date(p.created_at).toLocaleDateString('uk-UA')}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
