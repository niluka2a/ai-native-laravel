import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as api from '@/lib/api';
import type { HistoryItem, HistoryPage } from '@/lib/api';
import HistoryPageView from './page';

vi.mock('@/lib/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/lib/api')>()),
  fetchHistory: vi.fn(),
}));

const mocked = vi.mocked(api);

function makeItem(overrides: Partial<HistoryItem> = {}): HistoryItem {
  return {
    id: 1,
    requestId: 'req-1',
    requestMessage: 'Please refund my invoice',
    requestStatus: 'in_progress',
    category: 'billing',
    confidence: 0.86,
    provider: 'keyword',
    createdAt: '2026-11-13T10:00:00.000000Z',
    ...overrides,
  };
}

function makePage(data: HistoryItem[], next: string | null = null, prev: string | null = null, current = 1): HistoryPage {
  return {
    data,
    links: { first: 'x', last: null, prev, next },
    meta: { current_page: current, from: 1, to: data.length, per_page: 50, path: 'x' },
  };
}

function renderPage() {
  const client = new QueryClient({
    defaultOptions: {
      queries: { staleTime: 10_000, refetchOnWindowFocus: false, retry: false },
    },
  });
  return render(
    <QueryClientProvider client={client}>
      <HistoryPageView />
    </QueryClientProvider>,
  );
}

describe('HistoryPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  afterEach(() => {
    cleanup();
  });

  it('shows a loading state, then the rows', async () => {
    mocked.fetchHistory.mockResolvedValue(makePage([makeItem()]));

    renderPage();
    expect(screen.getByText('Loading history…')).toBeTruthy();

    await waitFor(() => expect(screen.getByText('Please refund my invoice')).toBeTruthy());
    expect(screen.getByText('86%')).toBeTruthy();
    expect(screen.getByText('keyword')).toBeTruthy();
    expect(screen.getByText('in_progress')).toBeTruthy();
    expect(within(screen.getByRole('table')).getByText('billing')).toBeTruthy();
  });

  it('lists all categories and refetches page 1 for the chosen one', async () => {
    mocked.fetchHistory.mockResolvedValue(makePage([makeItem()]));

    renderPage();
    await waitFor(() => expect(screen.getByRole('table')).toBeTruthy());

    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(Array.from(select.options).map((o) => o.text)).toEqual([
      'All categories',
      'support',
      'sales',
      'billing',
      'unknown',
    ]);

    fireEvent.change(select, { target: { value: 'billing' } });

    await waitFor(() =>
      expect(mocked.fetchHistory).toHaveBeenLastCalledWith({ category: 'billing', page: 1 }),
    );
  });

  it('shows the empty state telling staff to classify a request', async () => {
    mocked.fetchHistory.mockResolvedValue(makePage([]));

    renderPage();

    await waitFor(() => expect(screen.getByText(/No classifications yet/)).toBeTruthy());
    expect(screen.getByRole('link', { name: 'requests page' }).getAttribute('href')).toBe('/');
  });

  it('shows an error with a retry button', async () => {
    mocked.fetchHistory.mockRejectedValueOnce(new Error('boom'));
    mocked.fetchHistory.mockResolvedValue(makePage([makeItem()]));

    renderPage();
    await waitFor(() => expect(screen.getByText('Could not load history.')).toBeTruthy());

    fireEvent.click(screen.getByRole('button', { name: 'Retry' }));

    await waitFor(() => expect(screen.getByText('Please refund my invoice')).toBeTruthy());
  });

  it('disables Next on the last page and requests page 2 otherwise', async () => {
    mocked.fetchHistory.mockImplementation(async ({ page } = {}) =>
      page === 2
        ? makePage([makeItem({ id: 2, requestMessage: 'Second page' })], null, 'prev', 2)
        : makePage([makeItem()], 'next'),
    );

    renderPage();
    await waitFor(() => expect(screen.getByRole('table')).toBeTruthy());
    expect((screen.getByRole('button', { name: 'Previous' }) as HTMLButtonElement).disabled).toBe(true);

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));

    await waitFor(() => expect(screen.getByText('Second page')).toBeTruthy());
    expect(mocked.fetchHistory).toHaveBeenLastCalledWith({ category: undefined, page: 2 });
    expect((screen.getByRole('button', { name: 'Next' }) as HTMLButtonElement).disabled).toBe(true);
  });

  it('disables paging while the next page is loading', async () => {
    let resolvePage2: (value: HistoryPage) => void = () => {};
    mocked.fetchHistory.mockImplementation(({ page } = {}) =>
      page === 2
        ? new Promise<HistoryPage>((resolve) => {
            resolvePage2 = resolve;
          })
        : Promise.resolve(makePage([makeItem()], 'next')),
    );

    renderPage();
    await waitFor(() => expect(screen.getByRole('table')).toBeTruthy());

    fireEvent.click(screen.getByRole('button', { name: 'Next' }));

    // Page 1 stays on screen, but both buttons are disabled until page 2 arrives.
    await waitFor(() =>
      expect((screen.getByRole('button', { name: 'Next' }) as HTMLButtonElement).disabled).toBe(true),
    );
    expect((screen.getByRole('button', { name: 'Previous' }) as HTMLButtonElement).disabled).toBe(true);
    expect(screen.getByText('Please refund my invoice')).toBeTruthy();

    resolvePage2(makePage([makeItem({ id: 2, requestMessage: 'Second page' })], null, 'prev', 2));

    await waitFor(() => expect(screen.getByText('Second page')).toBeTruthy());
    expect((screen.getByRole('button', { name: 'Previous' }) as HTMLButtonElement).disabled).toBe(false);
  });
});
