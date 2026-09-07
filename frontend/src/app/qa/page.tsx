import Link from 'next/link';
import { api } from '@/lib/api';
import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Юридичні консультації — питання та відповіді' };
export const dynamic = 'force-dynamic';

export default async function QaPage({ searchParams }: { searchParams: Record<string, string> }) {
  const qs = new URLSearchParams(searchParams).toString();
  const [questions, categories] = await Promise.all([
    api.questions(qs ? `?${qs}` : '').catch(() => null),
    api.categories().catch(() => null),
  ]);

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <div className="flex items-center justify-between">
        <h1 className="text-3xl font-bold">Питання та відповіді</h1>
        <Link href="/ask" className="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          Поставити питання
        </Link>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        <Link href="/qa" className={`rounded-full px-3 py-1 text-sm ${!searchParams.category ? 'bg-brand-600 text-white' : 'bg-white border'}`}>Усі</Link>
        {categories?.data.map((c) => (
          <Link key={c.slug} href={`/qa?category=${c.slug}`}
            className={`rounded-full px-3 py-1 text-sm ${searchParams.category === c.slug ? 'bg-brand-600 text-white' : 'bg-white border'}`}>
            {c.name}
          </Link>
        ))}
      </div>

      <div className="mt-6 space-y-3">
        {questions?.data.map((q) => (
          <Link key={q.id} href={`/qa/${q.id}`} className="block rounded-xl border bg-white p-5 hover:shadow-sm">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="font-semibold">{q.title}</h2>
                <div className="mt-1 text-xs text-gray-500">
                  {q.category.name} · {q.author_name} · {new Date(q.created_at).toLocaleDateString('uk-UA')}
                </div>
              </div>
              <div className="shrink-0 text-center">
                <div className={`rounded-lg px-3 py-1 text-sm font-medium ${q.answers_count > 0 ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                  {q.answers_count}
                </div>
                <div className="mt-1 text-[10px] text-gray-400">відповідей</div>
              </div>
            </div>
          </Link>
        )) ?? <p className="text-gray-500">Немає даних.</p>}
      </div>
    </div>
  );
}
