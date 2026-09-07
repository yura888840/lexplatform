// API-клиент LexPlatform.
// SSR: внутренний URL (nginx в docker-сети).
// Браузер: NEXT_PUBLIC_API_URL если задан на этапе build, иначе относительный /api/v1 —
//   запрос идёт на тот же домен, откуда загружена страница (Caddy проксирует /api → backend).
//   Относительный путь работает на любом домене без пересборки.
const isServer = typeof window === 'undefined';
const BASE = isServer
  ? process.env.API_INTERNAL_URL ?? 'http://nginx/api/v1'
  : process.env.NEXT_PUBLIC_API_URL || '/api/v1';

export interface Paginated<T> {
  data: T[];
  meta: { total: number; page: number; per_page: number; pages: number };
}

export interface Lawyer {
  id: string; slug: string; full_name: string; avatar_url: string | null;
  city: string; specializations: { name: string; slug: string }[];
  rating: number; reviews_count: number; consultations_count: number;
  experience_years: number; hourly_rate: string | null;
  is_online: boolean; is_verified: boolean; is_featured: boolean;
  bio?: string; member_since?: string;
}

export interface Question {
  id: string; title: string; body?: string;
  category: { name: string; slug: string };
  status: string; author_name: string;
  views_count: number; answers_count: number; created_at: string;
  answers?: Answer[];
}

export interface Answer {
  id: string; body: string; is_accepted: boolean; helpful_votes: number;
  created_at: string; author: { full_name: string; avatar_url: string | null };
}

export interface Article {
  id: string; type: string; title: string; slug: string; excerpt: string;
  body?: string; cover_image: string | null; author_name: string;
  category: string | null; views_count: number; published_at: string | null;
}

export interface Category { id: string; name: string; slug: string }

export interface LawDocument {
  id: string; type: string; number: string; slug: string; title: string;
  body?: string; full_access?: boolean; is_pro_only: boolean;
  issued_by: string; issued_at: string; status: string; version: number;
  previous_versions?: { version: number; slug: string; status: string }[];
}

export interface Plan {
  id: string; name: string; slug: string; price: string; currency: string;
  interval: string; features: string[];
}

export interface LawyerCabinet {
  slug: string; contribution_mode: 'articles' | 'paid';
  compliance: Compliance;
  articles_published_count: number; last_article_at: string | null;
  content_score: number; effective_rating: number; is_verified: boolean;
  specializations: { id: string; name: string; slug: string }[];
  documents: { id: string; type: string; file_name: string; status: string; uploaded_at: string }[];
}

export interface Compliance {
  status: 'onboarding' | 'ok' | 'warning' | 'non_compliant';
  mode: string; specializations_total: number; specializations_covered: number;
  articles_published: number; last_article_at: string | null;
  cadence_target_days: number; hint: string;
}

export interface Specialization { id: string; name: string; slug: string }

export interface Account {
  id: string; email: string; full_name: string; phone: string | null;
  avatar_url: string | null; role: string; status: string; locale: string;
  member_since: string; stats: { questions: number; payments: number };
}

export interface AccountQuestion {
  id: string; title: string; category: string; status: string;
  answers_count: number; views_count: number; has_accepted: boolean; created_at: string;
}

export interface AccountPayment {
  id: string; order_id: string; type: string; type_label: string;
  amount: string; currency: string; status: string; created_at: string; paid_at: string | null;
}

export interface AdminDashboard {
  users: { total: number; clients: number; lawyers: number; banned: number; new_7d: number };
  moderation: { reviews_pending: number; documents_pending: number; articles_review: number };
  content: { questions: number; articles_published: number; lawyers_verified: number };
  revenue: { payments_succeeded: number; gross_total: string; commission_total: string };
}

export interface AdminUser {
  id: string; full_name: string; email: string; role: string; status: string; created_at: string;
}

export interface AdminReview {
  id: string; rating: number; body: string | null; author: string; lawyer: string; lawyer_slug: string; created_at: string;
}

export interface AdminDocument {
  id: string; type: string; file_name: string; lawyer: string; lawyer_slug: string; view_url: string | null; uploaded_at: string;
}

export interface CheckoutResponse {
  payment_id: string; order_id: string; checkout_url: string; data: string; signature: string;
}

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const token = !isServer ? localStorage.getItem('access_token') : null;
  const res = await fetch(`${BASE}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init?.headers,
    },
    cache: 'no-store',
  });
  if (!res.ok) {
    const problem = await res.json().catch(() => null);
    const message = problem?.detail ?? `Помилка API (${res.status})`;
    // 401 у браузері → сесія протухла: чистимо токени й ведемо на логін
    if (res.status === 401 && !isServer) {
      handleUnauthorized();
    }
    throw new ApiError(res.status, message);
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

/** Централізована реакція на 401: очищення сесії + редирект на /login з поверненням назад. */
export function handleUnauthorized() {
  try {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('user');
  } catch { /* SSR / приватний режим */ }
  const path = window.location.pathname + window.location.search;
  // не зациклюємось, якщо вже на /login
  if (!path.startsWith('/login')) {
    window.location.href = `/login?next=${encodeURIComponent(path)}`;
  }
}

export const api = {
  lawyers: (params = '') => request<Paginated<Lawyer>>(`/lawyers${params}`),
  lawyer: (slug: string) => request<Lawyer>(`/lawyers/${slug}`),
  questions: (params = '') => request<Paginated<Question>>(`/questions${params}`),
  question: (id: string) => request<Question>(`/questions/${id}`),
  createQuestion: (body: object) => request<Question>('/questions', { method: 'POST', body: JSON.stringify(body) }),
  articles: (params = '') => request<Paginated<Article>>(`/articles${params}`),
  article: (slug: string) => request<Article>(`/articles/${slug}`),
  categories: () => request<{ data: Category[] }>('/categories'),
  search: (q: string) => request<{ query: string; total: number; results: Record<string, unknown>[] }>(`/search?q=${encodeURIComponent(q)}`),
  laws: (params = '') => request<Paginated<LawDocument>>(`/laws${params}`),
  law: (slug: string) => request<LawDocument>(`/laws/${slug}`),
  plans: () => request<{ data: Plan[] }>('/subscriptions/plans'),
  mySubscription: () => request<{ active: boolean; plan?: string; expires_at?: string }>('/subscriptions/me'),
  checkout: (body: object) => request<CheckoutResponse>('/payments/checkout', { method: 'POST', body: JSON.stringify(body) }),
  paymentStatus: (orderId: string) => request<{ order_id: string; status: string }>(`/payments/status/${orderId}`),
  lawyerCabinet: () => request<LawyerCabinet>('/lawyers/me'),
  updateLawyerCabinet: (body: object) => request<{ ok: boolean; compliance: Compliance }>('/lawyers/me', { method: 'PATCH', body: JSON.stringify(body) }),
  submitArticle: (body: object) => request<{ id: string; slug: string; status: string; chars_count: number }>('/articles', { method: 'POST', body: JSON.stringify(body) }),
  myArticles: () => request<{ data: { id: string; title: string; status: string; specialization: string | null; chars_count: number }[] }>('/articles/mine'),
  payouts: () => request<{ pending_total: string; data: { gross: string; commission: string; net: string; status: string; created_at: string }[] }>('/payouts/me'),
  account: () => request<Account>('/account'),
  updateAccount: (body: object) => request<{ ok: boolean; full_name: string; phone: string | null }>('/account', { method: 'PATCH', body: JSON.stringify(body) }),
  accountQuestions: (params = '') => request<Paginated<AccountQuestion>>(`/account/questions${params}`),
  accountPayments: (params = '') => request<Paginated<AccountPayment>>(`/account/payments${params}`),
  // ── Адмін ──
  adminDashboard: () => request<AdminDashboard>('/admin/dashboard'),
  adminUsers: (params = '') => request<Paginated<AdminUser>>(`/admin/users${params}`),
  adminBanUser: (id: string) => request<{ id: string; status: string }>(`/admin/users/${id}/ban`, { method: 'POST' }),
  adminUnbanUser: (id: string) => request<{ id: string; status: string }>(`/admin/users/${id}/unban`, { method: 'POST' }),
  adminChangeRole: (id: string, role: string) => request<{ id: string; role: string }>(`/admin/users/${id}/role`, { method: 'PATCH', body: JSON.stringify({ role }) }),
  adminPendingReviews: () => request<{ data: AdminReview[] }>('/admin/reviews/pending'),
  adminApproveReview: (id: string) => request<{ id: string; status: string }>(`/admin/reviews/${id}/approve`, { method: 'POST' }),
  adminRejectReview: (id: string) => request<{ id: string; status: string }>(`/admin/reviews/${id}/reject`, { method: 'POST' }),
  adminPendingDocuments: () => request<{ data: AdminDocument[] }>('/admin/documents/pending'),
  adminApproveDocument: (id: string) => request<{ id: string; status: string }>(`/admin/documents/${id}/approve`, { method: 'POST', body: JSON.stringify({}) }),
  adminRejectDocument: (id: string, note: string) => request<{ id: string; status: string }>(`/admin/documents/${id}/reject`, { method: 'POST', body: JSON.stringify({ note }) }),
  register: (body: object) => request<AuthPayload>('/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  login: (body: object) => request<AuthPayload>('/auth/login', { method: 'POST', body: JSON.stringify(body) }),
  // Поточний користувач (для шапки). Кидає ApiError(401), якщо не залогінений.
  me: () => request<CurrentUser>('/auth/me'),
  logout: (refreshToken: string) => request<void>('/auth/logout', { method: 'POST', body: JSON.stringify({ refresh_token: refreshToken }) }),
};

export interface CurrentUser {
  id: string; email: string; full_name: string; role: string;
  locale?: string; avatar_url?: string | null;
}

export interface AuthPayload {
  user: { id: string; email: string; full_name: string; role: string };
  access_token: string;
  refresh_token: string;
}

export function saveAuth(p: AuthPayload) {
  localStorage.setItem('access_token', p.access_token);
  localStorage.setItem('refresh_token', p.refresh_token);
  localStorage.setItem('user', JSON.stringify(p.user));
}

export function clearAuth() {
  localStorage.removeItem('access_token');
  localStorage.removeItem('refresh_token');
  localStorage.removeItem('user');
}
