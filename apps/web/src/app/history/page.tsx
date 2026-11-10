'use client';

import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { fetchHistory } from '@/lib/api';

export default function HistoryPage() {
  const [category, setCategory] = useState('');
  const historyQuery = useQuery({
    queryKey: ['history', category],
    queryFn: () => fetchHistory(category || undefined),
  });

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-semibold">Classification history</h2>
        <p className="mt-1 text-sm text-slate-600">
          Persist classifications (schema + migration), then make this view list and filter
          them. The classifier belongs behind a provider interface that could later be an LLM
          — see core task 5 in the README.
        </p>
      </div>

      <label className="flex max-w-sm flex-col gap-1 text-sm">
        <span className="font-medium text-slate-700">Filter by category</span>
        <input
          className="rounded border border-slate-300 px-3 py-2"
          value={category}
          onChange={(e) => setCategory(e.target.value)}
          placeholder="billing | sales | support | unknown"
        />
      </label>

      <div className="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
        {historyQuery.isLoading ? (
          <p>Loading…</p>
        ) : historyQuery.isError ? (
          <p className="text-red-700">Failed to load history.</p>
        ) : (
          <pre className="overflow-x-auto whitespace-pre-wrap">
            {JSON.stringify(historyQuery.data, null, 2)}
          </pre>
        )}
      </div>
    </div>
  );
}
