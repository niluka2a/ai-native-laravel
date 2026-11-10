import type { Metadata } from 'next';
import './globals.css';
import { Providers } from './providers';

export const metadata: Metadata = {
  title: 'Request Desk',
  description: 'Customer request triage console',
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <Providers>
          <div className="min-h-screen">
            <header className="border-b border-slate-200 bg-white">
              <div className="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Engineering Challenge
                  </p>
                  <h1 className="text-lg font-semibold">Request Desk</h1>
                </div>
                <nav className="flex gap-4 text-sm">
                  <a className="text-slate-700 hover:text-slate-900" href="/">
                    Requests
                  </a>
                  <a className="text-slate-700 hover:text-slate-900" href="/history">
                    History
                  </a>
                </nav>
              </div>
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">{children}</main>
          </div>
        </Providers>
      </body>
    </html>
  );
}
