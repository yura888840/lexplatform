import { api } from '@/lib/api';
import { notFound } from 'next/navigation';

export const dynamic = 'force-dynamic';

export default async function LawyerProfilePage({ params }: { params: { slug: string } }) {
  const lawyer = await api.lawyer(params.slug).catch(() => null);
  if (!lawyer) notFound();

  return (
    <div className="mx-auto max-w-4xl px-4 py-10">
      <div className="rounded-2xl border bg-white p-8">
        <div className="flex items-start gap-6">
          <div className="flex h-24 w-24 items-center justify-center rounded-full bg-brand-50 text-3xl font-bold text-brand-700">
            {lawyer.full_name.slice(0, 1)}
          </div>
          <div className="flex-1">
            <h1 className="flex items-center gap-2 text-2xl font-bold">
              {lawyer.full_name}
              {lawyer.is_verified && <span className="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">Верифікований</span>}
              {lawyer.is_online && <span className="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Онлайн</span>}
            </h1>
            <p className="mt-1 text-gray-500">{lawyer.city} · {lawyer.experience_years} років досвіду</p>
            <div className="mt-2 flex flex-wrap gap-1">
              {lawyer.specializations.map((s) => (
                <span key={s.slug} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{s.name}</span>
              ))}
            </div>
          </div>
          <div className="text-right">
            <div className="text-xl font-semibold text-amber-500">★ {lawyer.rating.toFixed(1)}</div>
            <div className="text-xs text-gray-400">{lawyer.reviews_count} відгуків</div>
          </div>
        </div>

        {/* Статистика (ТЗ §7.1) */}
        <div className="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
          {[
            ['Консультацій', String(lawyer.consultations_count)],
            ['Досвід', `${lawyer.experience_years} р.`],
            ['Ціна', lawyer.hourly_rate ? `${Number(lawyer.hourly_rate).toLocaleString('uk-UA')} грн/год` : '—'],
            ['На платформі з', lawyer.member_since ?? '—'],
          ].map(([label, value]) => (
            <div key={label} className="rounded-lg bg-gray-50 p-4 text-center">
              <div className="text-lg font-semibold">{value}</div>
              <div className="text-xs text-gray-500">{label}</div>
            </div>
          ))}
        </div>

        {lawyer.bio && (
          <div className="mt-8">
            <h2 className="text-lg font-semibold">Про юриста</h2>
            <p className="mt-2 whitespace-pre-line text-gray-700">{lawyer.bio}</p>
          </div>
        )}

        <div className="mt-8 flex gap-3">
          <a href="/ask" className="rounded-lg bg-brand-600 px-6 py-3 font-medium text-white hover:bg-brand-700">Поставити питання</a>
        </div>
      </div>
    </div>
  );
}
