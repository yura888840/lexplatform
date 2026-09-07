'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { api, saveAuth } from '@/lib/api';

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({ full_name: '', email: '', password: '', role: 'client', accepts_personal_data: false });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      saveAuth(await api.register(form));
      router.push('/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Помилка реєстрації.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="mx-auto max-w-md px-4 py-14">
      <h1 className="mb-6 text-center text-3xl font-bold">Реєстрація</h1>
      <form onSubmit={submit} className="space-y-4 rounded-2xl border bg-white p-8">
        <div className="grid grid-cols-2 gap-2">
          {[['client', 'Я клієнт'], ['lawyer', 'Я юрист']].map(([value, label]) => (
            <button type="button" key={value}
              onClick={() => setForm({ ...form, role: value })}
              className={`rounded-lg border py-2 text-sm font-medium ${form.role === value ? 'border-brand-600 bg-brand-50 text-brand-700' : 'text-gray-600'}`}>
              {label}
            </button>
          ))}
        </div>
        <input required placeholder="Повне ім'я" value={form.full_name}
          onChange={(e) => setForm({ ...form, full_name: e.target.value })}
          className="w-full rounded-lg border p-3 text-sm" />
        <input required type="email" placeholder="Email" value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          className="w-full rounded-lg border p-3 text-sm" />
        <input required type="password" minLength={8} placeholder="Пароль (мін. 8 символів)" value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          className="w-full rounded-lg border p-3 text-sm" />
        {form.role === 'lawyer' && (
          <label className="flex items-start gap-2 rounded-lg bg-gray-50 p-3 text-xs text-gray-700">
            <input type="checkbox" required checked={form.accepts_personal_data}
              onChange={(e) => setForm({ ...form, accepts_personal_data: e.target.checked })} className="mt-0.5" />
            <span>Даю згоду на обробку та розповсюдження моїх персональних даних (ПІБ, фото, місто, спеціалізації)
            у публічному каталозі юристів LexPlatform.</span>
          </label>
        )}
        {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
        <button disabled={loading} className="w-full rounded-lg bg-brand-600 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
          {loading ? 'Створюємо…' : 'Створити акаунт'}
        </button>
        <p className="text-center text-sm text-gray-500">
          Вже є акаунт? <Link href="/login" className="text-brand-600 hover:underline">Увійти</Link>
        </p>
      </form>
    </div>
  );
}
