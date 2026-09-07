import Link from 'next/link';
import type { Lawyer } from '@/lib/api';

export default function LawyerCard({ lawyer }: { lawyer: Lawyer }) {
  return (
    <Link
      href={`/lawyers/${lawyer.slug}`}
      className={`block rounded-xl border bg-white p-5 transition hover:shadow-md ${lawyer.is_featured ? 'border-brand-500 ring-1 ring-brand-100' : 'border-gray-200'}`}
    >
      <div className="flex items-start gap-4">
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-brand-50 text-lg font-semibold text-brand-700">
          {lawyer.full_name.slice(0, 1)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <span className="truncate font-semibold text-gray-900">{lawyer.full_name}</span>
            {lawyer.is_verified && <span title="Верифікований" className="text-brand-600">✓</span>}
            {lawyer.is_online && <span className="h-2 w-2 rounded-full bg-green-500" title="Онлайн" />}
          </div>
          <p className="mt-0.5 text-sm text-gray-500">
            {lawyer.city} · {lawyer.experience_years} р. досвіду
          </p>
          <div className="mt-2 flex flex-wrap gap-1">
            {lawyer.specializations.map((s) => (
              <span key={s.slug} className="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{s.name}</span>
            ))}
          </div>
        </div>
        <div className="text-right">
          <div className="text-sm font-semibold text-amber-500">★ {lawyer.rating.toFixed(1)}</div>
          <div className="text-xs text-gray-400">{lawyer.reviews_count} відгуків</div>
          {lawyer.hourly_rate && (
            <div className="mt-2 text-sm font-medium text-gray-900">{Number(lawyer.hourly_rate).toLocaleString('uk-UA')} грн/год</div>
          )}
        </div>
      </div>
    </Link>
  );
}
