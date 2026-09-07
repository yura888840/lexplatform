'use client';

import { Suspense, useEffect, useState } from 'react';
import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { api } from '@/lib/api';

function ResultInner() {
  const params = useSearchParams();
  const [status, setStatus] = useState<string>('checking');

  useEffect(() => {
    const orderId = params.get('order_id') ?? sessionStorage.getItem('last_order_id');
    if (!orderId) { setStatus('unknown'); return; }

    let attempts = 0;
    const poll = async () => {
      try {
        const r = await api.paymentStatus(orderId);
        if (r.status === 'succeeded' || r.status === 'failed') { setStatus(r.status); return; }
      } catch { /* webhook мог ещё не прийти */ }
      if (++attempts < 10) setTimeout(poll, 2000);
      else setStatus('pending');
    };
    poll();
  }, [params]);

  const view: Record<string, [string, string]> = {
    checking: ['⏳', 'Перевіряємо статус оплати…'],
    pending: ['⏳', 'Оплата обробляється. Статус оновиться у кабінеті протягом кількох хвилин.'],
    succeeded: ['✅', 'Оплату отримано! Підписка активована.'],
    failed: ['❌', 'Оплата не пройшла. Спробуйте ще раз або оберіть інший спосіб.'],
    unknown: ['❓', 'Замовлення не знайдено.'],
  };
  const [icon, text] = view[status] ?? view.unknown;

  return (
    <div className="mx-auto max-w-md px-4 py-20 text-center">
      <div className="text-5xl">{icon}</div>
      <p className="mt-4 text-lg">{text}</p>
      <div className="mt-8 flex justify-center gap-3">
        <Link href="/laws" className="rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700">До бази законів</Link>
        <Link href="/pro" className="rounded-lg border px-5 py-2 text-sm hover:bg-gray-50">Тарифи</Link>
      </div>
    </div>
  );
}

export default function PaymentResultPage() {
  return <Suspense><ResultInner /></Suspense>;
}
