const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3001';

export type RequestStatus = 'open' | 'in_progress' | 'resolved';

/** Matches RequestStatus::label() in the API; the select needs a label for every status. */
export const STATUS_LABELS: Record<RequestStatus, string> = {
  open: 'Open',
  in_progress: 'In progress',
  resolved: 'Resolved',
};

export type RequestListItem = {
  id: string;
  message: string;
  status: RequestStatus;
  statusLabel: string;
  category: string | null;
  confidence: number | null;
  noteCount: number;
  latestNotePreview: string | null;
  createdAt: string;
  updatedAt: string;
};

export async function fetchRequests(): Promise<RequestListItem[]> {
  const res = await fetch(`${API_URL}/requests`);
  if (!res.ok) {
    throw new Error(`Failed to load requests (${res.status})`);
  }
  return res.json();
}

/** A request as returned by create and status update (no note fields). */
export type RequestSummary = Omit<RequestListItem, 'noteCount' | 'latestNotePreview'>;

export async function createRequest(message: string): Promise<RequestSummary> {
  const res = await fetch(`${API_URL}/requests`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message }),
  });
  if (!res.ok) {
    throw new Error(`Failed to create request (${res.status})`);
  }
  return res.json();
}

export async function updateRequestStatus(
  id: string,
  status: RequestStatus,
): Promise<RequestSummary> {
  const res = await fetch(`${API_URL}/requests/${id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  });
  if (!res.ok) {
    throw new Error(`Failed to update status (${res.status})`);
  }
  return res.json();
}

export const CATEGORIES = ['support', 'sales', 'billing', 'unknown'] as const;
export type Category = (typeof CATEGORIES)[number];

export type ClassifyResult = {
  category: Category;
  confidence: number;
  requestId: string | null;
};

export async function classifyMessage(
  message: string,
  requestId?: string,
): Promise<ClassifyResult> {
  const res = await fetch(`${API_URL}/requests/classify`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message, requestId }),
  });
  if (!res.ok) {
    throw new Error(`Failed to classify (${res.status})`);
  }
  return res.json();
}

export type HistoryItem = {
  id: number;
  requestId: string;
  requestMessage: string;
  requestStatus: RequestStatus;
  category: Category;
  confidence: number;
  provider: string;
  createdAt: string;
};

export type HistoryPage = {
  data: HistoryItem[];
  links: { first: string | null; last: string | null; prev: string | null; next: string | null };
  meta: { current_page: number; from: number | null; to: number | null; per_page: number; path: string };
};

export async function fetchHistory({
  category,
  page = 1,
}: {
  category?: Category;
  page?: number;
} = {}): Promise<HistoryPage> {
  const params = new URLSearchParams();
  if (category) params.set('category', category);
  params.set('page', String(page));
  const res = await fetch(`${API_URL}/requests/history?${params}`);
  if (!res.ok) {
    throw new Error(`Failed to load history (${res.status})`);
  }
  return res.json();
}
