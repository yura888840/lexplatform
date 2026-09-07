'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type LawyerCabinet } from '@/lib/api';

const MIN_CHARS = 3600; // «мінімум 2 сторінки» — синхронно с app.min_article_chars

export default function NewArticlePage() {
  const router = useRouter();
  const [cab, setCab] = useState<LawyerCabinet | null>(null);
  const [form, setForm] = useState({ title: '', body: '', specialization_id: '', accepts_copyright_transfer: false });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!localStorage.getItem('access_token')) { router.push('/login?next=/cabinet/articles/new'); return; }
    api.lawyerCabinet().then(setCab).catch(() => router.push('/cabinet'));
  }, [router]);

  const plainChars = form.body.replace(/<[^>]*>/g, '').trim().length;
  const progress = Math.min(100, Math.round((plainChars / MIN_CHARS) * 100));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      await api.submitArticle(form);
      router.push('/cabinet?submitted=1');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Помилка.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <h1 className="text-3xl font-bold">Нова стаття в блог</h1>
      <p className="mt-2 text-sm text-gray-600">
        Стаття підтверджує вашу компетенцію в обраній галузі, підвищує рейтинг і після схвалення редактором публікується в блозі порталу.
      </p>

      <form onSubmit={submit} className="mt-8 space-y-5 rounded-2xl border bg-white p-8">
        <div>
          <label className="text-sm font-medium">Галузь права</label>
          <select required value={form.specialization_id}
            onChange={(e) => setForm({ ...form, specialization_id: e.target.value })}
            className="mt-1 w-full rounded-lg border p-3 text-sm">
            <option value="">Оберіть зі своїх спеціалізацій</option>
            {cab?.specializations.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          <p className="mt-1 text-xs text-gray-400">Писати можна лише в галузі, обрані у вашому профілі.</p>
        </div>

        <div>
          <label className="text-sm font-medium">Заголовок</label>
          <input required minLength={15} value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            className="mt-1 w-full rounded-lg border p-3 text-sm" />
        </div>

        <div>
          <div className="flex items-center justify-between">
            <label className="text-sm font-medium">Текст статті</label>
            <span className={`text-xs ${plainChars >= MIN_CHARS ? 'text-green-600' : 'text-gray-400'}`}>
              {plainChars.toLocaleString('uk-UA')} / {MIN_CHARS.toLocaleString('uk-UA')} знаків (~2 стор.)
            </span>
          </div>
          <textarea required rows={18} value={form.body}
            onChange={(e) => setForm({ ...form, body: e.target.value })}
            placeholder="Практичний матеріал: розбір норми, кейс із практики, покрокова інструкція…"
            className="mt-1 w-full rounded-lg border p-3 text-sm" />
          <div className="mt-1 h-1.5 w-full rounded-full bg-gray-100">
            <div className={`h-1.5 rounded-full transition-all ${plainChars >= MIN_CHARS ? 'bg-green-500' : 'bg-brand-500'}`}
              style={{ width: `${progress}%` }} />
          </div>
        </div>

        <label className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 text-sm">
          <input type="checkbox" required checked={form.accepts_copyright_transfer}
            onChange={(e) => setForm({ ...form, accepts_copyright_transfer: e.target.checked })}
            className="mt-0.5" />
          <span>
            Я передаю порталу LexPlatform виключні майнові права на цю статтю та підтверджую,
            що є її автором. Згода фіксується з версією умов, датою та IP.
          </span>
        </label>

        {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}

        <button disabled={loading || plainChars < MIN_CHARS}
          className="w-full rounded-lg bg-brand-600 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {loading ? 'Надсилаємо…' : plainChars < MIN_CHARS ? `Ще ${(MIN_CHARS - plainChars).toLocaleString('uk-UA')} знаків` : 'Надіслати на модерацію'}
        </button>
      </form>
    </div>
  );
}
