'use client';

import Link from 'next/link';
import { useEffect, useRef, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api, clearAuth, ApiError, type CurrentUser } from '@/lib/api';

export default function Header() {
  const router = useRouter();
  const [user, setUser] = useState<CurrentUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  // Джерело правди — запит до /auth/me при завантаженні.
  // localStorage лише для миттєвого показу до відповіді (уникаємо блимання).
  useEffect(() => {
    const cached = localStorage.getItem('user');
    if (cached) {
      try { setUser(JSON.parse(cached)); } catch { /* ignore */ }
    }
    if (!localStorage.getItem('access_token')) {
      setUser(null);
      setLoading(false);
      return;
    }
    api.me()
      .then((u) => {
        setUser(u);
        localStorage.setItem('user', JSON.stringify(u));
      })
      .catch((e) => {
        if (e instanceof ApiError && e.status === 401) setUser(null);
      })
      .finally(() => setLoading(false));
  }, []);

  // Закриття меню по кліку поза ним
  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    }
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, []);

  async function logout() {
    const refresh = localStorage.getItem('refresh_token');
    try {
      if (refresh) await api.logout(refresh);
    } catch { /* навіть якщо не вийшло — чистимо локально */ }
    clearAuth();
    setUser(null);
    setMenuOpen(false);
    router.push('/');
    router.refresh();
  }

  const isLawyer = user?.role === 'lawyer';
  const isStaff = user ? ['admin', 'superadmin', 'moderator', 'editor'].includes(user.role) : false;
  const cabinetHref = isLawyer ? '/cabinet' : '/account';

  return (
    <header className="border-b bg-white sticky top-0 z-20">
      <div className="mx-auto max-w-6xl flex items-center justify-between px-4 h-16">
        <Link href="/" className="text-xl font-bold text-brand-700">Lex<span className="text-brand-500">Platform</span></Link>
        <nav className="hidden md:flex gap-6 text-sm text-gray-700">
          <Link href="/lawyers" className="hover:text-brand-600">Каталог юристів</Link>
          <Link href="/qa" className="hover:text-brand-600">Консультації</Link>
          <Link href="/articles" className="hover:text-brand-600">Публікації</Link>
          <Link href="/laws" className="hover:text-brand-600">Законодавство</Link>
          <Link href="/pro" className="font-medium text-brand-600 hover:text-brand-700">PRO</Link>
        </nav>

        <div className="flex items-center gap-3">
          {isStaff && <Link href="/admin" className="text-sm font-medium text-slate-700 hover:text-slate-900">Адмін</Link>}
          <Link href="/ask" className="hidden sm:inline-flex rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Отримати консультацію
          </Link>

          {loading ? (
            <div className="h-8 w-20 animate-pulse rounded-lg bg-gray-100" />
          ) : user ? (
            <div className="relative" ref={menuRef}>
              <button
                onClick={() => setMenuOpen((v) => !v)}
                className="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                aria-haspopup="true"
                aria-expanded={menuOpen}
              >
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700 overflow-hidden">
                  {user.avatar_url
                    ? <img src={user.avatar_url} alt="" className="h-7 w-7 rounded-full object-cover" />
                    : user.full_name.slice(0, 1).toUpperCase()}
                </span>
                <span className="hidden sm:inline max-w-[140px] truncate">{user.full_name}</span>
                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" className={`text-gray-400 transition-transform ${menuOpen ? 'rotate-180' : ''}`}>
                  <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clipRule="evenodd" />
                </svg>
              </button>

              {menuOpen && (
                <div className="absolute right-0 mt-2 w-56 rounded-xl border bg-white py-1 shadow-lg">
                  <div className="border-b px-4 py-3">
                    <div className="truncate text-sm font-medium text-gray-900">{user.full_name}</div>
                    <div className="truncate text-xs text-gray-400">{user.email}</div>
                  </div>
                  <Link href={cabinetHref} onClick={() => setMenuOpen(false)}
                    className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                    {isLawyer ? 'Кабінет юриста' : 'Мій кабінет'}
                  </Link>
                  {isLawyer && (
                    <Link href="/cabinet/profile" onClick={() => setMenuOpen(false)}
                      className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                      Керування профілем
                    </Link>
                  )}
                  {isStaff && (
                    <Link href="/admin" onClick={() => setMenuOpen(false)}
                      className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                      Адмін-панель
                    </Link>
                  )}
                  <button onClick={logout}
                    className="block w-full border-t px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                    Вийти
                  </button>
                </div>
              )}
            </div>
          ) : (
            <Link href="/login" className="text-sm font-medium text-gray-700 hover:text-brand-600">Вхід</Link>
          )}
        </div>
      </div>
    </header>
  );
}
