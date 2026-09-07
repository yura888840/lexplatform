import { api } from '@/lib/api';
import LawyerCard from '@/components/LawyerCard';
import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Каталог юристів' };
export const dynamic = 'force-dynamic';

export default async function LawyersPage({ searchParams }: { searchParams: Record<string, string> }) {
  const qs = new URLSearchParams(searchParams).toString();
  const [lawyers, categories] = await Promise.all([
    api.lawyers(qs ? `?${qs}` : '').catch(() => null),
    api.categories().catch(() => null),
  ]);

  return (
    <div className="mx-auto max-w-6xl px-4 py-10 md:flex md:gap-8">
      {/* Sidebar фильтров (ТЗ §7.1) */}
      <aside className="mb-6 md:mb-0 md:w-64 shrink-0">
        <form className="space-y-4 rounded-xl border bg-white p-5" method="get">
          <div>
            <label className="text-sm font-medium">Спеціалізація</label>
            <select name="specialization" defaultValue={searchParams.specialization ?? ''} className="mt-1 w-full rounded-lg border p-2 text-sm">
              <option value="">Усі</option>
              {categories?.data.map((c) => <option key={c.slug} value={c.slug}>{c.name}</option>)}
            </select>
          </div>
          <div>
            <label className="text-sm font-medium">Місто</label>
            <input name="city" defaultValue={searchParams.city ?? ''} placeholder="Київ" className="mt-1 w-full rounded-lg border p-2 text-sm" />
          </div>
          <div>
            <label className="text-sm font-medium">Сортування</label>
            <select name="sort" defaultValue={searchParams.sort ?? 'rating'} className="mt-1 w-full rounded-lg border p-2 text-sm">
              <option value="rating">За рейтингом</option>
              <option value="reviews">За відгуками</option>
              <option value="experience">За досвідом</option>
              <option value="price_asc">Ціна ↑</option>
              <option value="price_desc">Ціна ↓</option>
            </select>
          </div>
          <label className="flex items-center gap-2 text-sm">
            <input type="checkbox" name="online" value="1" defaultChecked={searchParams.online === '1'} /> Онлайн зараз
          </label>
          <button className="w-full rounded-lg bg-brand-600 py-2 text-sm font-medium text-white hover:bg-brand-700">Застосувати</button>
        </form>
      </aside>

      <div className="flex-1">
        <h1 className="text-3xl font-bold">Каталог юристів</h1>
        <p className="mt-1 text-sm text-gray-500">Знайдено: {lawyers?.meta.total ?? 0}</p>
        <div className="mt-6 space-y-4">
          {lawyers?.data.map((l) => <LawyerCard key={l.id} lawyer={l} />)}
          {(!lawyers || lawyers.data.length === 0) && <p className="text-gray-500">Юристів за цими фільтрами не знайдено.</p>}
        </div>
      </div>
    </div>
  );
}
