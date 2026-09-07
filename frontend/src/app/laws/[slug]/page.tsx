import Link from 'next/link';
import { api } from '@/lib/api';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function LawPage({ params }: { params: { slug: string } }) {
  const doc = await api.law(params.slug).catch(() => null);
  if (!doc) notFound();

  return (
    <div className="mx-auto max-w-3xl px-4 py-10">
      <div className="text-xs uppercase tracking-wide text-brand-600">{doc.type} № {doc.number}</div>
      <h1 className="mt-1 text-3xl font-bold">{doc.title}</h1>
      <div className="mt-2 text-sm text-gray-500">
        {doc.issued_by} · від {doc.issued_at} · редакція {doc.version}
        {doc.status !== 'active' && <span className="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{doc.status}</span>}
      </div>

      {doc.previous_versions && doc.previous_versions.length > 0 && (
        <div className="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
          Попередні редакції:{' '}
          {doc.previous_versions.map((v) => (
            <Link key={v.slug} href={`/laws/${v.slug}`} className="mr-2 text-brand-600 hover:underline">ред. {v.version}</Link>
          ))}
        </div>
      )}

      <div className="mt-8 whitespace-pre-line leading-relaxed text-gray-800">{doc.body}</div>

      {doc.full_access === false && (
        <div className="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-6 text-center">
          <p className="font-medium text-amber-900">Повний текст доступний за підпискою PRO</p>
          <Link href="/pro" className="mt-3 inline-block rounded-lg bg-brand-600 px-6 py-2 font-medium text-white hover:bg-brand-700">
            Оформити PRO
          </Link>
        </div>
      )}
    </div>
  );
}
