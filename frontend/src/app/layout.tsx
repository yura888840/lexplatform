import type { Metadata } from 'next';
import SiteChrome from '@/components/SiteChrome';
import './globals.css';

export const metadata: Metadata = {
  title: { default: 'LexPlatform — Юридична платформа нового покоління', template: '%s | LexPlatform' },
  description: 'Каталог юристів, безкоштовні консультації, юридична база знань. Отримайте відповідь юриста онлайн.',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="uk">
      <body className="min-h-screen bg-gray-50 text-gray-900 antialiased">
        <SiteChrome>{children}</SiteChrome>
      </body>
    </html>
  );
}
