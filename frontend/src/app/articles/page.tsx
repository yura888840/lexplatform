import Link from 'next/link';
import { api } from '@/lib/api';
import type { Metadata } from 'next';

export const metadata: Metadata = { title: 'Публікації' };
export const dynamic = 'force-dynamic';

export default async function ArticlesPage() {
  const articles = await api.articles().catch(() => null);

  return (
    <div className="mx-auto max-w-5xl px-4 py-10">
      <h1 className="text-3xl font-bold">Публікації</h1>
      <div className="mt-6 grid gap-5 md:grid-cols-2">
        {articles?.data.map((a) => (
          <Link key={a.id} href={`/articles/${a.slug}`} className="rounded-xl border bg-white p-6 hover:shadow-sm">
            <div className="text-xs uppercase text-brand-600">{a.type}</div>
            <h2 className="mt-1 font-semibold">{a.title}</h2>
            <p className="mt-2 text-sm text-gray-600 line-clamp-3">{a.excerpt}</p>
            <div className="mt-3 text-xs text-gray-400">
              {a.author_name}{a.published_at ? ` · ${new Date(a.published_at).toLocaleDateString('uk-UA')}` : ''}
            </div>
          </Link>
        )) ?? <p className="text-gray-500">Немає даних.</p>}
      </div>
    </div>
  );
}
