'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { api, saveAuth } from '@/lib/api';
import { Suspense } from 'react';

function LoginForm() {
  const router = useRouter();
  const params = useSearchParams();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      saveAuth(await api.login(form));
      router.push(params.get('next') ?? '/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Помилка входу.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4 rounded-2xl border bg-white p-8">
      <input required type="email" placeholder="Email" value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        className="w-full rounded-lg border p-3 text-sm" />
      <input required type="password" placeholder="Пароль" value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        className="w-full rounded-lg border p-3 text-sm" />
      {error && <p className="rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
      <button disabled={loading} className="w-full rounded-lg bg-brand-600 py-3 font-medium text-white hover:bg-brand-700 disabled:opacity-50">
        {loading ? 'Вхід…' : 'Увійти'}
      </button>
      <p className="text-center text-sm text-gray-500">
        Немає акаунту? <Link href="/register" className="text-brand-600 hover:underline">Зареєструватися</Link>
      </p>
    </form>
  );
}

export default function LoginPage() {
  return (
    <div className="mx-auto max-w-md px-4 py-14">
      <h1 className="mb-6 text-center text-3xl font-bold">Вхід</h1>
      <Suspense><LoginForm /></Suspense>
    </div>
  );
}
