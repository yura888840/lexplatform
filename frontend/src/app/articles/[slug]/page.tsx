import { api } from '@/lib/api';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function ArticlePage({ params }: { params: { slug: string } }) {
  const a = await api.article(params.slug).catch(() => null);
  if (!a) notFound();

  return (
    <article className="mx-auto max-w-3xl px-4 py-10">
      <div className="text-xs uppercase tracking-wide text-brand-600">{a.type}</div>
      <h1 className="mt-1 text-3xl font-bold">{a.title}</h1>
      <div className="mt-2 text-sm text-gray-500">
        {a.author_name}{a.published_at ? ` · ${new Date(a.published_at).toLocaleDateString('uk-UA')}` : ''} · переглядів: {a.views_count}
      </div>
      <div className="prose prose-gray mt-8 max-w-none" dangerouslySetInnerHTML={{ __html: a.body ?? '' }} />
    </article>
  );
}
