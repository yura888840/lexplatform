'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type Category } from '@/lib/api';

export default function AskPage() {
  const router = useRouter();
  const [categories, setCategories] = useState<Category[]>([]);
  const [form, setForm] = useState({ title: '', body: '', category_id: '', is_anonymous: false });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    api.categories().then((r) => setCategories(r.data)).catch(() => {});
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    if (!localStorage.getItem('access_token')) {
      router.push('/login?next=/ask');
      return;
    }
    setLoading(true);
    try {
      const q = await api.createQuestion(form);
      router.push(`/qa/${q.id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Помилка. Спробуйте ще раз.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-10">
      <h1 className="text-3xl font-bold">Поставити питання юристу</h1>
      <p className="mt-2 text-gray-600">Питання публічне та безкоштовне. Юристи відповідають зазвичай протягом кількох годин.</p>

      <form onSubmit={submit} className="mt-8 space-y-5 rounded-2xl border bg-white p-8">
        <div>
          <label className="text-sm font-medium">Категорія</label>
          <select required value={form.category_id}
            onChange={(e) => setForm({ ...form, category_id: e.target.value })}
            className="mt-1 w-full rounded-lg border p-3 text-sm">
            <option value="">Оберіть галузь права</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
        <div>
          <label className="text-sm font-medium">Заголовок питання</label>
          <input required minLength={10} value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            placeholder="Коротко сформулюйте суть"
            className="mt-1 w-full rounded-lg border p-3 text-sm" />
        </div>
        <div>
          <label className="text-sm font-medium">Опис ситуації</label>
          <textarea required minLength={30} rows={7} value={form.body}
            onChange={(e) => setForm({ ...form, body: e.target.value })}
            placeholder="Опишіть обставини: що сталося, коли, які документи є"
            className="mt-1 w-full rounded-lg border p-3 text-sm" />
        </div>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={form.is_anonymous}
            onChange={(e) => setForm({ ...form, is_anonymous: e.target.checked })} />
          Опублікувати анонімно
        </label>
        {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
        <button disabled={loading} className="w-full rounded-lg bg-brand-600 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {loading ? 'Надсилаємо…' : 'Опублікувати питання'}
        </button>
      </form>
    </div>
  );
}
