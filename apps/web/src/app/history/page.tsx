'use client';

import { keepPreviousData, useQuery } from '@tanstack/react-query';
import Link from 'next/link';
import { useState } from 'react';
import { CATEGORIES, Category, fetchHistory } from '@/lib/api';

const MESSAGE_PREVIEW_LENGTH = 80;

function preview(message: string) {
  return message.length > MESSAGE_PREVIEW_LENGTH
    ? `${message.slice(0, MESSAGE_PREVIEW_LENGTH)}…`
    : message;
}

export default function HistoryPage() {
  const [category, setCategory] = useState<Category | ''>('');
  const [page, setPage] = useState(1);

  const historyQuery = useQuery({
    queryKey: ['history', category, page],
    queryFn: () => fetchHistory({ category: category || undefined, page }),
    placeholderData: keepPreviousData,
  });

  const history = historyQuery.data;
  // While the next page loads, the previous one stays on screen; block paging until it lands.
  const isPaging = historyQuery.isPlaceholderData;

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold">Classification history</h2>
        <p className="mt-1 text-sm text-slate-600">
          Every classification stored on a request, newest first.
        </p>
      </div>

      <label className="flex max-w-sm flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">Filter by category</span>
        <select
          className="rounded border border-slate-300 bg-white px-3 py-2"
          value={category}
          onChange={(e) => {
            setCategory(e.target.value as Category | '');
            setPage(1);
          }}
        >
          <option value="">All categories</option>
          {CATEGORIES.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
      </label>

      {historyQuery.isLoading ? (
        <p className="text-slate-600">Loading history…</p>
      ) : historyQuery.isError ? (
        <div className="space-y-2 text-sm">
          <p className="text-red-700">Could not load history.</p>
          <button
            type="button"
            className="rounded bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-700"
            onClick={() => historyQuery.refetch()}
          >
            Retry
          </button>
        </div>
      ) : history && history.data.length === 0 ? (
        <p className="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
          {category ? (
            `No classifications for ${category}.`
          ) : (
            <>
              No classifications yet. Classify a request on the{' '}
              <Link href="/" className="underline">
                requests page
              </Link>{' '}
              and it will show up here.
            </>
          )}
        </p>
      ) : history ? (
        <>
          <div className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <table className="min-w-full divide-y divide-slate-200 text-sm">
              <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                  <th className="px-4 py-3">When</th>
                  <th className="px-4 py-3">Request</th>
                  <th className="px-4 py-3">Category</th>
                  <th className="px-4 py-3">Confidence</th>
                  <th className="px-4 py-3">Provider</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {history.data.map((item) => (
                  <tr key={item.id}>
                    <td className="whitespace-nowrap px-4 py-3">
                      {new Date(item.createdAt).toLocaleString()}
                    </td>
                    <td className="max-w-md px-4 py-3">
                      <div className="font-medium text-slate-900">
                        {preview(item.requestMessage)}
                      </div>
                      <div className="mt-1 text-xs text-slate-500">{item.requestStatus}</div>
                    </td>
                    <td className="px-4 py-3">{item.category}</td>
                    <td className="px-4 py-3 tabular-nums">
                      {Math.round(item.confidence * 100)}%
                    </td>
                    <td className="px-4 py-3">{item.provider}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="flex items-center gap-3 text-sm">
            <button
              type="button"
              className="rounded border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-50"
              disabled={isPaging || !history.links.prev}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous
            </button>
            <span className="text-slate-600">Page {history.meta.current_page}</span>
            <button
              type="button"
              className="rounded border border-slate-300 bg-white px-3 py-1.5 disabled:opacity-50"
              disabled={isPaging || !history.links.next}
              onClick={() => setPage((p) => p + 1)}
            >
              Next
            </button>
          </div>
        </>
      ) : null}
    </div>
  );
}
