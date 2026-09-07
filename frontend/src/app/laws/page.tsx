import Link from 'next/link';
import { api } from '@/lib/api';
import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'База законодавства' };
export const dynamic = 'force-dynamic';

const TYPES: Record<string, string> = {
  code: 'Кодекс', law: 'Закон', resolution: 'Постанова',
  order: 'Наказ', decree: 'Указ', international: 'Міжнародний акт',
};

export default async function LawsPage({ searchParams }: { searchParams: Record<string, string> }) {
  const qs = new URLSearchParams(searchParams).toString();
  const laws = await api.laws(qs ? `?${qs}` : '').catch(() => null);

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="text-3xl font-bold">База законодавства</h1>
      <p className="mt-1 text-sm text-gray-500">Повнотекстовий пошук по НПА. Знайдено: {laws?.meta.total ?? 0}</p>

      <form method="get" className="mt-4 flex gap-2">
        <input name="q" defaultValue={searchParams.q ?? ''} placeholder='Пошук: "оренда житла", кодекс…'
          className="flex-1 rounded-lg border p-3 text-sm" />
        <select name="type" defaultValue={searchParams.type ?? ''} className="rounded-lg border p-3 text-sm">
          <option value="">Усі типи</option>
          {Object.entries(TYPES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        <button className="rounded-lg bg-brand-600 px-6 text-sm font-medium text-white hover:bg-brand-700">Знайти</button>
      </form>

      <div className="mt-6 space-y-3">
        {laws?.data.map((d) => (
          <Link key={d.id} href={`/laws/${d.slug}`} className="block rounded-xl border bg-white p-5 hover:shadow-sm">
            <div className="flex items-start justify-between gap-4">
              <div>
                <div className="text-xs uppercase text-brand-600">{TYPES[d.type] ?? d.type} № {d.number}</div>
                <h2 className="mt-0.5 font-semibold">{d.title}</h2>
                <div className="mt-1 text-xs text-gray-500">
                  {d.issued_by} · {d.issued_at} · ред. {d.version}
                </div>
              </div>
              <div className="flex shrink-0 flex-col items-end gap-1">
                <span className={`rounded-full px-2 py-0.5 text-xs ${d.status === 'active' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                  {d.status === 'active' ? 'Чинний' : d.status === 'amended' ? 'Змінений' : 'Нечинний'}
                </span>
                {d.is_pro_only && <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">PRO</span>}
              </div>
            </div>
          </Link>
        )) ?? <p className="text-gray-500">Немає даних.</p>}
      </div>
    </div>
  );
}
