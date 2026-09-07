'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  api, type AdminDashboard, type AdminUser, type AdminReview, type AdminDocument,
} from '@/lib/api';

type Tab = 'dashboard' | 'users' | 'reviews' | 'documents';

const NAV: [Tab, string][] = [
  ['dashboard', 'Огляд'],
  ['users', 'Користувачі'],
  ['reviews', 'Модерація відгуків'],
  ['documents', 'Верифікація документів'],
];

export default function AdminPage() {
  const router = useRouter();
  const [tab, setTab] = useState<Tab>('dashboard');
  const [authorized, setAuthorized] = useState<boolean | null>(null);
  const [role, setRole] = useState<string>('');
  const [pending, setPending] = useState({ reviews: 0, documents: 0 });

  const isFullAdmin = ['admin', 'superadmin'].includes(role);
  const nav = NAV.filter(([id]) => isFullAdmin || (id !== 'dashboard' && id !== 'users'));

  useEffect(() => {
    if (!localStorage.getItem('access_token')) { router.push('/login?next=/admin'); return; }
    const raw = localStorage.getItem('user');
    const r = raw ? JSON.parse(raw).role : null;
    if (!['admin', 'superadmin', 'moderator'].includes(r)) {
      setAuthorized(false);
      return;
    }
    setRole(r);
    setAuthorized(true);
    // Модератор стартує з модерації відгуків, адмін — з дашборду
    const fullAdmin = ['admin', 'superadmin'].includes(r);
    setTab(fullAdmin ? 'dashboard' : 'reviews');
    if (fullAdmin) {
      api.adminDashboard().then((d) => setPending({ reviews: d.moderation.reviews_pending, documents: d.moderation.documents_pending })).catch(() => {});
    } else {
      Promise.all([api.adminPendingReviews(), api.adminPendingDocuments()])
        .then(([rv, dc]) => setPending({ reviews: rv.data.length, documents: dc.data.length })).catch(() => {});
    }
  }, [router]);

  if (authorized === null) return <div className="p-16 text-center text-slate-400">Завантаження…</div>;
  if (!authorized) return (
    <div className="mx-auto max-w-md px-4 py-24 text-center">
      <div className="text-4xl">🔒</div>
      <h1 className="mt-4 text-xl font-bold text-slate-800">Доступ заборонено</h1>
      <p className="mt-2 text-sm text-slate-500">Ця сторінка доступна лише адміністраторам.</p>
      <button onClick={() => router.push('/')} className="mt-6 rounded-lg bg-slate-800 px-5 py-2 text-sm font-medium text-white hover:bg-slate-900">На головну</button>
    </div>
  );

  return (
    <div className="min-h-screen bg-slate-50">
      {/* Топбар */}
      <header className="sticky top-0 z-30 border-b bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
          <div className="flex items-center gap-2">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800 text-sm font-bold text-white">L</span>
            <span className="font-semibold text-slate-800">Адмін-панель</span>
          </div>
          <button onClick={() => router.push('/')} className="text-sm text-slate-500 hover:text-slate-700">← На сайт</button>
        </div>
      </header>

      <div className="mx-auto max-w-6xl gap-6 px-4 py-6 md:flex">
        {/* Навигация */}
        <aside className="mb-4 shrink-0 md:mb-0 md:w-56">
          <nav className="space-y-0.5">
            {nav.map(([id, label]) => (
              <button key={id} onClick={() => setTab(id)}
                className={`flex w-full items-center justify-between rounded-lg px-3 py-2 text-sm transition ${tab === id ? 'bg-slate-800 font-medium text-white' : 'text-slate-600 hover:bg-slate-100'}`}>
                <span>{label}</span>
                {id === 'reviews' && pending.reviews > 0 && <Pill n={pending.reviews} on={tab === id} />}
                {id === 'documents' && pending.documents > 0 && <Pill n={pending.documents} on={tab === id} />}
              </button>
            ))}
          </nav>
        </aside>

        <main className="min-w-0 flex-1">
          {tab === 'dashboard' && <DashboardTab />}
          {tab === 'users' && <UsersTab />}
          {tab === 'reviews' && <ReviewsTab onChange={(n) => setPending((p) => ({ ...p, reviews: n }))} />}
          {tab === 'documents' && <DocumentsTab onChange={(n) => setPending((p) => ({ ...p, documents: n }))} />}
        </main>
      </div>
    </div>
  );
}

function Pill({ n, on }: { n: number; on: boolean }) {
  return <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${on ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700'}`}>{n}</span>;
}

function DashboardTab() {
  const [d, setD] = useState<AdminDashboard | null>(null);
  useEffect(() => { api.adminDashboard().then(setD).catch(() => {}); }, []);
  if (!d) return <div className="py-10 text-center text-slate-400">Завантаження…</div>;

  const Stat = ({ label, value, tone = 'slate' }: { label: string; value: string | number; tone?: string }) => (
    <div className="rounded-xl border bg-white p-4">
      <div className="text-xs text-slate-500">{label}</div>
      <div className={`mt-1 text-2xl font-bold tabular-nums ${tone === 'amber' ? 'text-amber-600' : tone === 'red' ? 'text-red-600' : 'text-slate-800'}`}>{value}</div>
    </div>
  );

  return (
    <div className="space-y-6">
      <div>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Користувачі</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
          <Stat label="Усього" value={d.users.total} />
          <Stat label="Клієнти" value={d.users.clients} />
          <Stat label="Юристи" value={d.users.lawyers} />
          <Stat label="Заблоковані" value={d.users.banned} tone={d.users.banned ? 'red' : 'slate'} />
          <Stat label="Нові за 7 днів" value={d.users.new_7d} />
        </div>
      </div>

      <div>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Черга модерації</h2>
        <div className="grid grid-cols-3 gap-3">
          <Stat label="Відгуки" value={d.moderation.reviews_pending} tone={d.moderation.reviews_pending ? 'amber' : 'slate'} />
          <Stat label="Документи" value={d.moderation.documents_pending} tone={d.moderation.documents_pending ? 'amber' : 'slate'} />
          <Stat label="Статті" value={d.moderation.articles_review} tone={d.moderation.articles_review ? 'amber' : 'slate'} />
        </div>
      </div>

      <div>
        <h2 className="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">Контент і дохід</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Stat label="Питання" value={d.content.questions} />
          <Stat label="Статті" value={d.content.articles_published} />
          <Stat label="Верифіковані юристи" value={d.content.lawyers_verified} />
          <Stat label="Комісія платформи" value={`${Number(d.revenue.commission_total).toLocaleString('uk-UA')} ₴`} />
        </div>
      </div>
    </div>
  );
}

function UsersTab() {
  const [users, setUsers] = useState<AdminUser[] | null>(null);
  const [q, setQ] = useState('');
  const [role, setRole] = useState('');
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    const p = new URLSearchParams();
    if (q) p.set('q', q);
    if (role) p.set('role', role);
    const qs = p.toString();
    api.adminUsers(qs ? `?${qs}` : '').then((r) => setUsers(r.data)).catch(() => setUsers([]));
  }, [q, role]);

  useEffect(() => { load(); }, [role]); // eslint-disable-line react-hooks/exhaustive-deps

  async function ban(u: AdminUser) {
    setBusy(u.id);
    try { u.status === 'banned' ? await api.adminUnbanUser(u.id) : await api.adminBanUser(u.id); load(); }
    catch (e) { alert(e instanceof Error ? e.message : 'Помилка'); }
    finally { setBusy(null); }
  }

  async function changeRole(u: AdminUser, newRole: string) {
    if (newRole === u.role) return;
    setBusy(u.id);
    try { await api.adminChangeRole(u.id, newRole); load(); }
    catch (e) { alert(e instanceof Error ? e.message : 'Помилка'); }
    finally { setBusy(null); }
  }

  return (
    <div>
      <div className="mb-4 flex gap-2">
        <input value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => e.key === 'Enter' && load()}
          placeholder="Пошук за ім'ям або email…" className="flex-1 rounded-lg border p-2.5 text-sm focus:border-slate-400 focus:outline-none" />
        <select value={role} onChange={(e) => setRole(e.target.value)} className="rounded-lg border p-2.5 text-sm">
          <option value="">Усі ролі</option>
          <option value="client">Клієнти</option>
          <option value="lawyer">Юристи</option>
          <option value="moderator">Модератори</option>
          <option value="editor">Редактори</option>
          <option value="admin">Адміни</option>
        </select>
        <button onClick={load} className="rounded-lg bg-slate-800 px-4 text-sm font-medium text-white hover:bg-slate-900">Пошук</button>
      </div>

      {!users ? <div className="py-10 text-center text-slate-400">Завантаження…</div> : (
        <div className="overflow-hidden rounded-xl border bg-white">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-slate-50/60 text-left text-xs font-medium uppercase tracking-wide text-slate-400">
                <th className="px-4 py-3">Користувач</th><th className="px-4 py-3">Роль</th><th className="px-4 py-3">Статус</th><th className="px-4 py-3 text-right">Дії</th>
              </tr>
            </thead>
            <tbody>
              {users.map((u) => (
                <tr key={u.id} className="border-b border-slate-50 last:border-0 hover:bg-slate-50/40">
                  <td className="px-4 py-3">
                    <div className="font-medium text-slate-800">{u.full_name}</div>
                    <div className="text-xs text-slate-400">{u.email}</div>
                  </td>
                  <td className="px-4 py-3">
                    <select value={u.role} disabled={busy === u.id || ['admin', 'superadmin'].includes(u.role)}
                      onChange={(e) => changeRole(u, e.target.value)}
                      className="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs disabled:bg-slate-50 disabled:text-slate-400">
                      {['client', 'lawyer', 'moderator', 'editor', 'admin', 'superadmin'].map((r) => <option key={r} value={r}>{r}</option>)}
                    </select>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`rounded-md px-2 py-0.5 text-xs font-medium ${u.status === 'banned' ? 'bg-red-50 text-red-700' : u.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>
                      {u.status === 'banned' ? 'Заблокований' : u.status === 'active' ? 'Активний' : u.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    {!['admin', 'superadmin'].includes(u.role) && (
                      <button onClick={() => ban(u)} disabled={busy === u.id}
                        className={`rounded-md px-3 py-1 text-xs font-medium ${u.status === 'banned' ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-red-50 text-red-700 hover:bg-red-100'} disabled:opacity-50`}>
                        {u.status === 'banned' ? 'Розблокувати' : 'Заблокувати'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {users.length === 0 && <tr><td colSpan={4} className="px-4 py-10 text-center text-slate-400">Нічого не знайдено</td></tr>}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function ReviewsTab({ onChange }: { onChange: (n: number) => void }) {
  const [items, setItems] = useState<AdminReview[] | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    api.adminPendingReviews().then((r) => { setItems(r.data); onChange(r.data.length); }).catch(() => setItems([]));
  }, [onChange]);
  useEffect(() => { load(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function act(id: string, kind: 'approve' | 'reject') {
    setBusy(id);
    try { kind === 'approve' ? await api.adminApproveReview(id) : await api.adminRejectReview(id); load(); }
    catch (e) { alert(e instanceof Error ? e.message : 'Помилка'); }
    finally { setBusy(null); }
  }

  if (!items) return <div className="py-10 text-center text-slate-400">Завантаження…</div>;
  if (items.length === 0) return <EmptyState icon="✓" title="Черга порожня" text="Немає відгуків, що очікують модерації." />;

  return (
    <div className="space-y-3">
      {items.map((r) => (
        <div key={r.id} className="rounded-xl border bg-white p-4">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <div className="flex">{[1, 2, 3, 4, 5].map((i) => <span key={i} className={i <= r.rating ? 'text-amber-400' : 'text-slate-200'}>★</span>)}</div>
                <span className="text-sm font-medium text-slate-700">{r.author}</span>
                <span className="text-xs text-slate-400">→ {r.lawyer}</span>
              </div>
              {r.body && <p className="mt-2 text-sm text-slate-600">{r.body}</p>}
              <div className="mt-1 text-xs text-slate-400">{new Date(r.created_at).toLocaleDateString('uk-UA')}</div>
            </div>
            <div className="flex shrink-0 gap-2">
              <button onClick={() => act(r.id, 'approve')} disabled={busy === r.id}
                className="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50">Схвалити</button>
              <button onClick={() => act(r.id, 'reject')} disabled={busy === r.id}
                className="rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">Відхилити</button>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

function DocumentsTab({ onChange }: { onChange: (n: number) => void }) {
  const [items, setItems] = useState<AdminDocument[] | null>(null);
  const [busy, setBusy] = useState<string | null>(null);

  const load = useCallback(() => {
    api.adminPendingDocuments().then((r) => { setItems(r.data); onChange(r.data.length); }).catch(() => setItems([]));
  }, [onChange]);
  useEffect(() => { load(); }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function approve(id: string) {
    setBusy(id);
    try { await api.adminApproveDocument(id); load(); }
    catch (e) { alert(e instanceof Error ? e.message : 'Помилка'); }
    finally { setBusy(null); }
  }
  async function reject(id: string) {
    const note = prompt('Причина відхилення:');
    if (!note) return;
    setBusy(id);
    try { await api.adminRejectDocument(id, note); load(); }
    catch (e) { alert(e instanceof Error ? e.message : 'Помилка'); }
    finally { setBusy(null); }
  }

  const TYPE_LABEL: Record<string, string> = {
    advocate_certificate: 'Свідоцтво адвоката',
    education_certificate: 'Свідоцтво про освіту',
  };

  if (!items) return <div className="py-10 text-center text-slate-400">Завантаження…</div>;
  if (items.length === 0) return <EmptyState icon="✓" title="Черга порожня" text="Немає документів на верифікації." />;

  return (
    <div className="space-y-3">
      {items.map((doc) => (
        <div key={doc.id} className="flex items-center gap-4 rounded-xl border bg-white p-4">
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-500">📄</span>
          <div className="min-w-0 flex-1">
            <div className="truncate text-sm font-medium text-slate-800">{TYPE_LABEL[doc.type] ?? doc.type}</div>
            <div className="text-xs text-slate-400">{doc.lawyer} · {doc.file_name} · {new Date(doc.uploaded_at).toLocaleDateString('uk-UA')}</div>
          </div>
          <div className="flex shrink-0 gap-2">
            {doc.view_url && (
              <a href={doc.view_url} target="_blank" rel="noopener noreferrer"
                className="rounded-md border px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">Переглянути</a>
            )}
            <button onClick={() => approve(doc.id)} disabled={busy === doc.id}
              className="rounded-md bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100 disabled:opacity-50">Схвалити</button>
            <button onClick={() => reject(doc.id)} disabled={busy === doc.id}
              className="rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100 disabled:opacity-50">Відхилити</button>
          </div>
        </div>
      ))}
    </div>
  );
}

function EmptyState({ icon, title, text }: { icon: string; title: string; text: string }) {
  return (
    <div className="rounded-xl border border-dashed bg-white py-16 text-center">
      <div className="text-3xl text-emerald-400">{icon}</div>
      <div className="mt-2 font-medium text-slate-600">{title}</div>
      <div className="text-sm text-slate-400">{text}</div>
    </div>
  );
}
