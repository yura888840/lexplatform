'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, type Plan } from '@/lib/api';

/** Оплата LiqPay: POST-форма с data+signature авто-сабмитом на checkout_url. */
function submitLiqPayForm(checkoutUrl: string, data: string, signature: string) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = checkoutUrl;
  form.acceptCharset = 'utf-8';
  for (const [name, value] of [['data', data], ['signature', signature]]) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
}

export default function ProPage() {
  const router = useRouter();
  const [plans, setPlans] = useState<Plan[]>([]);
  const [active, setActive] = useState<{ plan?: string; expires_at?: string } | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState<string | null>(null);

  useEffect(() => {
    api.plans().then((r) => setPlans(r.data)).catch(() => {});
    if (localStorage.getItem('access_token')) {
      api.mySubscription().then((s) => s.active && setActive(s)).catch(() => {});
    }
  }, []);

  async function buy(planSlug: string) {
    setError(null);
    if (!localStorage.getItem('access_token')) {
      router.push('/login?next=/pro');
      return;
    }
    setLoading(planSlug);
    try {
      const c = await api.checkout({ type: 'subscription', plan_slug: planSlug });
      sessionStorage.setItem('last_order_id', c.order_id);
      submitLiqPayForm(c.checkout_url, c.data, c.signature);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Помилка оплати.');
      setLoading(null);
    }
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-14">
      <h1 className="text-center text-3xl font-bold">Підписка PRO</h1>
      <p className="mx-auto mt-2 max-w-xl text-center text-gray-600">
        Повний доступ до бази законодавства, судової практики та рішень ЄСПЛ.
      </p>

      {active && (
        <div className="mx-auto mt-6 max-w-md rounded-xl border border-green-300 bg-green-50 p-4 text-center text-sm text-green-800">
          У вас активна підписка «{active.plan}» до {active.expires_at ? new Date(active.expires_at).toLocaleDateString('uk-UA') : ''}.
          Оплата нижче продовжить її термін.
        </div>
      )}

      <div className="mt-10 grid gap-6 md:grid-cols-2">
        {plans.map((p) => (
          <div key={p.id} className={`rounded-2xl border bg-white p-8 ${p.interval === 'year' ? 'border-brand-500 ring-1 ring-brand-100' : ''}`}>
            {p.interval === 'year' && <div className="mb-2 text-xs font-semibold uppercase text-brand-600">Вигідніше на 30%</div>}
            <h2 className="text-xl font-bold">{p.name}</h2>
            <div className="mt-2 text-3xl font-bold">
              {Number(p.price).toLocaleString('uk-UA')} <span className="text-base font-normal text-gray-500">грн / {p.interval === 'year' ? 'рік' : 'міс'}</span>
            </div>
            <ul className="mt-4 space-y-2 text-sm text-gray-700">
              {p.features.map((f) => <li key={f}>✓ {f}</li>)}
            </ul>
            <button onClick={() => buy(p.slug)} disabled={loading !== null}
              className="mt-6 w-full rounded-lg bg-brand-600 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
              {loading === p.slug ? 'Перехід до оплати…' : 'Оплатити через LiqPay'}
            </button>
          </div>
        ))}
      </div>

      {error && <p className="mt-6 rounded-lg bg-red-50 p-3 text-center text-sm text-red-700">{error}</p>}
      <p className="mt-8 text-center text-xs text-gray-400">
        Оплата обробляється LiqPay (ПриватБанк). Платіжні дані не зберігаються на LexPlatform.
      </p>
    </div>
  );
}
