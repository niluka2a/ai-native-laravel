import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as api from '@/lib/api';
import type { RequestListItem } from '@/lib/api';
import HomePage from './page';

vi.mock('@/lib/api', () => ({
  fetchRequests: vi.fn(),
  createRequest: vi.fn(),
  updateRequestStatus: vi.fn(),
  classifyMessage: vi.fn(),
}));

const mocked = vi.mocked(api);

function makeRow(overrides: Partial<RequestListItem> = {}): RequestListItem {
  return {
    id: 'req-1',
    message: 'Please refund my invoice',
    status: 'open',
    category: null,
    confidence: null,
    noteCount: 0,
    latestNotePreview: null,
    createdAt: '2026-01-01T00:00:00Z',
    updatedAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

let queryClient: QueryClient;

function renderPage() {
  // Same defaults as providers.tsx (staleTime 10s), plus retry: false for tests.
  const client = (queryClient = new QueryClient({
    defaultOptions: {
      queries: { staleTime: 10_000, refetchOnWindowFocus: false, retry: false },
    },
  }));
  return render(
    <QueryClientProvider client={client}>
      <HomePage />
    </QueryClientProvider>,
  );
}

const statusSelect = () => screen.getByRole('combobox') as HTMLSelectElement;

describe('HomePage row actions', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  afterEach(() => {
    cleanup();
  });

  it('shows a changed status immediately and keeps it after the refetch', async () => {
    const pending = deferred<unknown>();
    mocked.fetchRequests
      .mockResolvedValueOnce([makeRow()])
      .mockResolvedValue([makeRow({ status: 'in_progress' })]);
    mocked.updateRequestStatus.mockReturnValue(pending.promise);

    renderPage();
    await waitFor(() => expect(statusSelect().value).toBe('open'));

    fireEvent.change(statusSelect(), { target: { value: 'in_progress' } });

    // Optimistic: the select shows the new value before the API call settles.
    await waitFor(() => expect(statusSelect().value).toBe('in_progress'));
    expect(mocked.fetchRequests).toHaveBeenCalledTimes(1);

    pending.resolve({});
    await waitFor(() => expect(mocked.fetchRequests).toHaveBeenCalledTimes(2));
    await waitFor(() => expect(statusSelect().value).toBe('in_progress'));
  });

  it('keeps the select disabled after a failed update until the refetch lands', async () => {
    const refetch = deferred<RequestListItem[]>();
    mocked.fetchRequests
      .mockResolvedValueOnce([makeRow()])
      .mockReturnValueOnce(refetch.promise);
    mocked.updateRequestStatus.mockRejectedValue(new Error('boom'));

    renderPage();
    await waitFor(() => expect(statusSelect().value).toBe('open'));

    fireEvent.change(statusSelect(), { target: { value: 'resolved' } });

    // The update has failed and the refetch is pending: the select stays locked.
    await waitFor(() => expect(mocked.fetchRequests).toHaveBeenCalledTimes(2));
    expect(statusSelect().disabled).toBe(true);

    refetch.resolve([makeRow()]);
    await waitFor(() => expect(statusSelect().disabled).toBe(false));
    expect(statusSelect().value).toBe('open');
  });

  it('shows the classified category, confidence and server-set status', async () => {
    mocked.fetchRequests
      .mockResolvedValueOnce([makeRow()])
      .mockResolvedValue([
        makeRow({ category: 'billing', confidence: 0.86, status: 'in_progress' }),
      ]);
    mocked.classifyMessage.mockResolvedValue({
      category: 'billing',
      confidence: 0.86,
      requestId: 'req-1',
    });

    renderPage();
    await waitFor(() => expect(statusSelect().value).toBe('open'));

    fireEvent.click(screen.getByRole('button', { name: 'Classify' }));

    await waitFor(() => expect(screen.getByText(/billing/)).toBeTruthy());
    expect(screen.getByText('(0.86)')).toBeTruthy();
    expect(statusSelect().value).toBe('in_progress');
  });

  it('disables every row control while an action is pending', async () => {
    const rows = [makeRow(), makeRow({ id: 'req-2', message: 'Cannot log in' })];
    const classifyPending = deferred<api.ClassifyResult>();
    const statusPending = deferred<unknown>();
    mocked.fetchRequests.mockResolvedValue(rows);
    mocked.updateRequestStatus.mockReturnValue(statusPending.promise);
    mocked.classifyMessage.mockReturnValue(classifyPending.promise);

    renderPage();
    await waitFor(() => expect(screen.getAllByRole('combobox')).toHaveLength(2));

    const controls = () => [
      ...(screen.getAllByRole('combobox') as HTMLSelectElement[]),
      ...(screen.getAllByRole('button', { name: 'Classify' }) as HTMLButtonElement[]),
    ];
    expect(controls().every((el) => !el.disabled)).toBe(true);

    // Status change pending.
    fireEvent.change(screen.getAllByRole('combobox')[0], { target: { value: 'resolved' } });
    await waitFor(() => expect(controls().every((el) => el.disabled)).toBe(true));
    statusPending.resolve({});
    await waitFor(() => expect(controls().every((el) => !el.disabled)).toBe(true));

    // Classify pending.
    fireEvent.click(screen.getAllByRole('button', { name: 'Classify' })[1]);
    await waitFor(() => expect(controls().every((el) => el.disabled)).toBe(true));
    classifyPending.resolve({ category: 'billing', confidence: 0.86, requestId: 'req-2' });
    await waitFor(() => expect(controls().every((el) => !el.disabled)).toBe(true));
  });

  it('invalidates the history queries after a classify', async () => {
    mocked.fetchRequests.mockResolvedValue([makeRow()]);
    mocked.classifyMessage.mockResolvedValue({
      category: 'billing',
      confidence: 0.86,
      requestId: 'req-1',
    });

    renderPage();
    await waitFor(() => expect(statusSelect().value).toBe('open'));
    const spy = vi.spyOn(queryClient, 'invalidateQueries');

    fireEvent.click(screen.getByRole('button', { name: 'Classify' }));

    await waitFor(() => expect(spy).toHaveBeenCalledWith({ queryKey: ['history'] }));
    expect(spy).toHaveBeenCalledWith({ queryKey: ['requests'] });
  });
});
