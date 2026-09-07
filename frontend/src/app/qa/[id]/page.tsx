import { api } from '@/lib/api';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function QuestionPage({ params }: { params: { id: string } }) {
  const q = await api.question(params.id).catch(() => null);
  if (!q) notFound();

  return (
    <div className="mx-auto max-w-4xl px-4 py-10">
      <div className="rounded-2xl border bg-white p-8">
        <div className="text-xs uppercase tracking-wide text-brand-600">{q.category.name}</div>
        <h1 className="mt-1 text-2xl font-bold">{q.title}</h1>
        <div className="mt-2 text-sm text-gray-500">
          {q.author_name} · {new Date(q.created_at).toLocaleDateString('uk-UA')} · переглядів: {q.views_count}
        </div>
        <p className="mt-6 whitespace-pre-line text-gray-800">{q.body}</p>
      </div>

      <h2 className="mt-10 text-xl font-bold">Відповіді юристів ({q.answers?.length ?? 0})</h2>
      <div className="mt-4 space-y-4">
        {q.answers?.map((a) => (
          <div key={a.id} className={`rounded-xl border bg-white p-6 ${a.is_accepted ? 'border-green-400 ring-1 ring-green-100' : ''}`}>
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 font-semibold text-brand-700">
                {a.author.full_name.slice(0, 1)}
              </div>
              <div>
                <div className="font-medium">{a.author.full_name}</div>
                <div className="text-xs text-gray-400">{new Date(a.created_at).toLocaleDateString('uk-UA')}</div>
              </div>
              {a.is_accepted && <span className="ml-auto rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700">✓ Обрана відповідь</span>}
            </div>
            <p className="mt-4 whitespace-pre-line text-gray-800">{a.body}</p>
          </div>
        ))}
        {(!q.answers || q.answers.length === 0) && (
          <p className="text-gray-500">Поки що немає відповідей. Юристи побачать питання найближчим часом.</p>
        )}
      </div>
    </div>
  );
}
