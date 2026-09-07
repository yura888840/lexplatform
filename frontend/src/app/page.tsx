import Link from 'next/link';
import { api } from '@/lib/api';
import LawyerCard from '@/components/LawyerCard';

export const dynamic = 'force-dynamic';

export default async function HomePage() {
  const [lawyers, questions, articles] = await Promise.all([
    api.lawyers('?per_page=3').catch(() => null),
    api.questions('?per_page=4').catch(() => null),
    api.articles('?per_page=3').catch(() => null),
  ]);

  return (
    <div>
      {/* Hero (ТЗ §7.1) */}
      <section className="bg-brand-900 py-20 text-white">
        <div className="mx-auto max-w-6xl px-4 text-center">
          <h1 className="text-4xl font-bold md:text-5xl">Юридична допомога онлайн</h1>
          <p className="mx-auto mt-4 max-w-2xl text-lg text-brand-100">
            Поставте питання безкоштовно, оберіть юриста з каталогу або замовте консультацію.
          </p>
          <div className="mt-8 flex justify-center gap-4">
            <Link href="/ask" className="rounded-lg bg-white px-6 py-3 font-semibold text-brand-900 hover:bg-brand-50">
              Поставити питання
            </Link>
            <Link href="/lawyers" className="rounded-lg border border-brand-100/40 px-6 py-3 font-semibold hover:bg-brand-700">
              Знайти юриста
            </Link>
          </div>
        </div>
      </section>

      {/* Как это работает */}
      <section className="mx-auto max-w-6xl px-4 py-14">
        <div className="grid gap-6 md:grid-cols-3">
          {[
            ['1. Опишіть проблему', 'Поставте питання — це безкоштовно та може бути анонімно.'],
            ['2. Отримайте відповіді', 'Практикуючі юристи відповідають публічно у Q&A.'],
            ['3. Замовте послугу', 'Оберіть юриста та продовжіть роботу приватно.'],
          ].map(([title, text]) => (
            <div key={title} className="rounded-xl border bg-white p-6">
              <h3 className="font-semibold">{title}</h3>
              <p className="mt-2 text-sm text-gray-600">{text}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Featured lawyers */}
      {lawyers && lawyers.data.length > 0 && (
        <section className="mx-auto max-w-6xl px-4 pb-14">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="text-2xl font-bold">Рекомендовані юристи</h2>
            <Link href="/lawyers" className="text-sm text-brand-600 hover:underline">Весь каталог →</Link>
          </div>
          <div className="grid gap-4 md:grid-cols-3">
            {lawyers.data.map((l) => <LawyerCard key={l.id} lawyer={l} />)}
          </div>
        </section>
      )}

      {/* Последние вопросы и публикации */}
      <section className="mx-auto max-w-6xl gap-8 px-4 pb-14 md:grid md:grid-cols-2">
        <div>
          <h2 className="mb-4 text-2xl font-bold">Останні питання</h2>
          <div className="space-y-3">
            {questions?.data.map((q) => (
              <Link key={q.id} href={`/qa/${q.id}`} className="block rounded-lg border bg-white p-4 hover:shadow-sm">
                <div className="font-medium">{q.title}</div>
                <div className="mt-1 text-xs text-gray-500">{q.category.name} · відповідей: {q.answers_count}</div>
              </Link>
            )) ?? <p className="text-gray-500">Немає даних.</p>}
          </div>
        </div>
        <div className="mt-8 md:mt-0">
          <h2 className="mb-4 text-2xl font-bold">Публікації</h2>
          <div className="space-y-3">
            {articles?.data.map((a) => (
              <Link key={a.id} href={`/articles/${a.slug}`} className="block rounded-lg border bg-white p-4 hover:shadow-sm">
                <div className="text-xs uppercase text-brand-600">{a.type}</div>
                <div className="font-medium">{a.title}</div>
                <p className="mt-1 text-sm text-gray-600 line-clamp-2">{a.excerpt}</p>
              </Link>
            )) ?? <p className="text-gray-500">Немає даних.</p>}
          </div>
        </div>
      </section>
    </div>
  );
}
