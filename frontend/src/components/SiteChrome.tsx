'use client';

import { usePathname } from 'next/navigation';
import Header from '@/components/Header';

/**
 * Обгортка сайту: показує загальний Header і footer скрізь,
 * окрім кабінету (/cabinet/*), який має власну липку шапку.
 */
export default function SiteChrome({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const isCabinet = pathname?.startsWith('/cabinet');
  const isAdmin = pathname?.startsWith('/admin');

  if (isCabinet || isAdmin) return <>{children}</>;

  return (
    <>
      <Header />
      <main>{children}</main>
      <footer className="mt-16 border-t bg-white py-8 text-center text-sm text-gray-500">
        © 2026 LexPlatform. Інформація на сайті не є юридичною консультацією.
      </footer>
    </>
  );
}
