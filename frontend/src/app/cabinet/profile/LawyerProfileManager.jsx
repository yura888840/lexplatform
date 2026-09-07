import React, { useState, useEffect, useRef, useCallback, useMemo } from "react";
import {
  User, Phone, Scale, MapPin, Briefcase, Tag, BookOpen, Gavel, FileText,
  Newspaper, Star, BarChart3, CreditCard, Search, Bell, Lock, Check, Plus,
  Trash2, Upload, Eye, EyeOff, ChevronRight, Globe, Clock, TrendingUp,
  Award, ShieldCheck, X, Image as ImageIcon, GripVertical, ExternalLink,
  Flag, CornerUpLeft, Info, Sparkles,
} from "lucide-react";

/* ============================================================================
   LexPlatform — Керування профілем юриста
   Дашборд редагування власного профілю (не публічна сторінка).
   Стиль: преміальний SaaS (Stripe / Linear / Notion), світла тема, укр. мова.
============================================================================ */

const NAV = [
  { id: "profile", label: "Профіль", icon: User },
  { id: "contacts", label: "Контакти", icon: Phone },
  { id: "legal", label: "Юридична інформація", icon: Scale },
  { id: "locations", label: "Офіси", icon: MapPin },
  { id: "practice", label: "Галузі права", icon: Tag },
  { id: "services", label: "Послуги та ціни", icon: Briefcase },
  { id: "publications", label: "Публікації", icon: Newspaper },
  { id: "cases", label: "Судові справи", icon: Gavel },
  { id: "documents", label: "Документи", icon: FileText },
  { id: "reviews", label: "Відгуки", icon: Star },
  { id: "stats", label: "Статистика", icon: BarChart3 },
  { id: "subscription", label: "Підписка", icon: CreditCard },
  { id: "seo", label: "SEO", icon: Search },
  { id: "notifications", label: "Сповіщення", icon: Bell },
  { id: "privacy", label: "Приватність", icon: Lock },
];

/* ---- realistic dummy state ------------------------------------------------ */
const INITIAL = {
  profile: {
    type: "lawyer",
    fullName: "Ігор Шевченко",
    companyName: "АБ «Шевченко та Партнери»",
    headline: "Адвокат з кримінальних та цивільних справ · 12 років практики",
    summary:
      "Захищаю інтереси клієнтів у кримінальних провадженнях і складних цивільних спорах. Веду справи в судах усіх інстанцій, включно з Верховним Судом.",
    languages: ["Українська", "Англійська", "Польська"],
    experience: 12,
    website: "https://shevchenko-law.ua",
    videoUrl: "https://youtube.com/watch?v=demo",
    avatar: null,
    cover: null,
    logo: null,
    gallery: [],
  },
  contacts: {
    phone: "+380 67 123 45 67", email: "i.shevchenko@lexplatform.ua",
    telegram: "@shevchenko_law", whatsapp: "+380671234567", signal: "",
    viber: "+380671234567", facebook: "fb.com/shevchenkolaw",
    linkedin: "linkedin.com/in/shevchenko", instagram: "@shevchenko.law",
    youtube: "", address: "вул. Хрещатик, 15, оф. 42",
    city: "Київ", region: "Київська область", country: "Україна",
  },
  legal: {
    barNumber: "№ 4521/10", dateIssued: "2013-04-16",
    authority: "Рада адвокатів м. Києва", region: "Київ",
    edrpou: "38472910", legalForm: "Адвокатське бюро",
    vat: "UA384729100000", registryUrl: "https://erau.unba.org.ua/profile/4521",
    verifiedPhone: true, verifiedEmail: true, verifiedDocs: true,
  },
  languagesAll: ["Українська", "Англійська", "Польська", "Німецька", "Французька", "Іспанська"],
  practiceAll: [
    "Кримінальні справи", "Цивільне право", "Сімейне право", "Військове право",
    "Господарське право", "Корпоративне право", "Податкове право", "Нерухомість",
    "Міграційне право", "Трудове право", "Банкрутство", "Міжнародне право",
    "Спадщина", "Авторське право", "ДТП: відшкодування",
  ],
  practice: { selected: ["Кримінальні справи", "Цивільне право", "Військове право"], primary: "Кримінальні справи" },
  locations: [
    { id: 1, name: "Головний офіс", address: "вул. Хрещатик, 15", city: "Київ", country: "Україна",
      coords: "50.4501, 30.5234", hours: "Пн–Пт 09:00–19:00", online: true, appointment: true },
    { id: 2, name: "Філія Львів", address: "пр. Свободи, 28", city: "Львів", country: "Україна",
      coords: "49.8397, 24.0297", hours: "Пн–Пт 10:00–18:00", online: true, appointment: false },
  ],
  services: [
    { id: 1, name: "Консультація", desc: "Усна консультація з правового питання", price: 1500, currency: "UAH", duration: "1 год", online: true, offline: true, visible: true },
    { id: 2, name: "Аналіз договору", desc: "Перевірка та правки договору", price: 2500, currency: "UAH", duration: "2 дні", online: true, offline: false, visible: true },
    { id: 3, name: "Представництво в суді", desc: "Ведення справи в суді першої інстанції", price: 25000, currency: "UAH", duration: "від 1 міс", online: false, offline: true, visible: true },
    { id: 4, name: "Апеляційна скарга", desc: "Складання та подання скарги", price: 8000, currency: "UAH", duration: "5 днів", online: true, offline: true, visible: false },
  ],
  free: { consultation: true, questions: true, docReview: false, assessment: true },
  pricing: { hourly: 2000, minProject: 5000, fixed: true, successFee: true, payments: ["Картка", "Безготівковий", "LiqPay"] },
  cases: [
    { id: 1, title: "Закриття кримінального провадження за ст. 190", category: "Кримінальні справи", year: 2024, result: "Виграно", desc: "Доведено відсутність складу злочину", visible: true },
    { id: 2, title: "Стягнення боргу 1.2 млн грн", category: "Цивільне право", year: 2023, result: "Виграно", desc: "Повне задоволення позову", visible: true },
    { id: 3, title: "Оскарження призову", category: "Військове право", year: 2024, result: "Частково", desc: "Відстрочка надана", visible: false },
  ],
  documents: [
    { id: 1, name: "Свідоцтво адвоката.pdf", type: "Свідоцтво", size: "1.2 МБ", status: "approved" },
    { id: 2, name: "Диплом магістра права.pdf", type: "Диплом", size: "2.4 МБ", status: "approved" },
    { id: 3, name: "Сертифікат ВША.pdf", type: "Сертифікат", size: "0.8 МБ", status: "pending" },
  ],
  publications: [
    { id: 1, title: "Як оскаржити незаконне звільнення у 2026", type: "Стаття", published: "2026-01-12", views: 3421, likes: 187, status: "published" },
    { id: 2, title: "Розподіл майна при розлученні: покроково", type: "Гайд", published: "2025-12-03", views: 5210, likes: 302, status: "published" },
    { id: 3, title: "Бронювання працівників: свіжа практика", type: "Стаття", published: "—", views: 0, likes: 0, status: "review" },
  ],
  reviews: [
    { id: 1, author: "Олена К.", rating: 5, date: "2026-01-08", text: "Дуже професійний підхід, виграли складну справу. Рекомендую!", reply: "Дякую за довіру, Олено!", hidden: false },
    { id: 2, author: "Анонім", rating: 5, date: "2026-01-02", text: "Швидко відповів і допоміг із документами.", reply: null, hidden: false },
    { id: 3, author: "Максим Т.", rating: 3, date: "2025-12-20", text: "Консультація корисна, але довелося чекати відповіді.", reply: null, hidden: false },
  ],
  subscription: { plan: "pro", expires: "2026-08-01" },
  seo: {
    slug: "ihor-shevchenko",
    metaTitle: "Ігор Шевченко — адвокат з кримінальних справ у Києві",
    metaDescription: "Досвідчений адвокат у Києві. Кримінальні, цивільні та військові справи. 12 років практики, 340+ виграних справ.",
    canonical: "https://lexplatform.ua/lawyers/ihor-shevchenko",
    ogImage: null,
  },
  notifications: { email: true, push: true, telegram: true, weekly: true, monthly: false },
  privacy: { visibility: "public", showPhone: true, showEmail: false, showPrices: true, allowMessages: true, allowBooking: true, allowReviews: true },
};

/* ---- design tokens (inline, self-contained) ------------------------------ */
const FONT_IMPORT = `
@import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700&display=swap');
.font-display { font-family: 'Fraunces', Georgia, serif; }
.tnum { font-variant-numeric: tabular-nums; }
@keyframes lp-fade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
.lp-fade { animation: lp-fade .28s cubic-bezier(.22,.61,.36,1); }
@keyframes lp-pop { 0% { transform: scale(.96); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
.lp-pop { animation: lp-pop .2s ease-out; }
.lp-scroll::-webkit-scrollbar { width: 6px; }
.lp-scroll::-webkit-scrollbar-thumb { background: #d6dae6; border-radius: 3px; }
@media (prefers-reduced-motion: reduce) { .lp-fade, .lp-pop { animation: none; } }
`;

/* ---- small UI primitives -------------------------------------------------- */
function Switch({ checked, onChange, label }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      onClick={() => onChange(!checked)}
      className={`relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#2545b8] ${
        checked ? "bg-[#2545b8]" : "bg-slate-200"
      }`}
    >
      <span className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${checked ? "translate-x-5" : "translate-x-0.5"}`} />
    </button>
  );
}

function Field({ label, children, hint, error }) {
  return (
    <label className="block">
      <span className="mb-1.5 flex items-center gap-1.5 text-[13px] font-medium text-slate-700">{label}</span>
      {children}
      {hint && !error && <span className="mt-1 block text-xs text-slate-400">{hint}</span>}
      {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
    </label>
  );
}

const inputCls =
  "w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-300 transition focus:border-[#2545b8] focus:outline-none focus:ring-4 focus:ring-[#2545b8]/10";

function Input(props) {
  return <input {...props} className={`${inputCls} ${props.className || ""}`} />;
}

function Card({ id, title, description, icon: Icon, children, onSave, footer = true }) {
  return (
    <section id={id} className="scroll-mt-24">
      <div className="lp-fade overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_2px_rgba(16,24,40,.04),0_1px_3px_rgba(16,24,40,.04)]">
        <div className="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
          <div className="flex items-start gap-3">
            {Icon && (
              <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#14265e]/5 text-[#14265e]">
                <Icon size={18} strokeWidth={2} />
              </span>
            )}
            <div>
              <h2 className="font-display text-lg leading-tight text-[#14265e]" style={{ fontWeight: 600 }}>{title}</h2>
              {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
            </div>
          </div>
        </div>
        <div className="px-6 py-6">{children}</div>
        {footer && (
          <div className="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-3">
            <button className="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-500 hover:bg-slate-100">Скасувати</button>
            <button onClick={onSave} className="inline-flex items-center gap-1.5 rounded-lg bg-[#14265e] px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#1d347e] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#2545b8]">
              <Check size={15} /> Зберегти
            </button>
          </div>
        )}
      </div>
    </section>
  );
}

function Segmented({ options, value, onChange }) {
  return (
    <div className="inline-flex flex-wrap gap-1 rounded-xl bg-slate-100 p-1">
      {options.map((o) => (
        <button
          key={o.value}
          onClick={() => onChange(o.value)}
          className={`rounded-lg px-3.5 py-1.5 text-sm font-medium transition ${
            value === o.value ? "bg-white text-[#14265e] shadow-sm" : "text-slate-500 hover:text-slate-700"
          }`}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

function Badge({ children, tone = "slate" }) {
  const tones = {
    green: "bg-emerald-50 text-emerald-700 ring-emerald-600/20",
    amber: "bg-amber-50 text-amber-700 ring-amber-600/20",
    red: "bg-red-50 text-red-700 ring-red-600/20",
    blue: "bg-blue-50 text-blue-700 ring-blue-600/20",
    slate: "bg-slate-100 text-slate-600 ring-slate-500/15",
  };
  return <span className={`inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}>{children}</span>;
}

function UploadBox({ label, hint, tall, previewUrl, onFile }) {
  const ref = useRef(null);
  const [hover, setHover] = useState(false);
  return (
    <div>
      {label && <span className="mb-1.5 block text-[13px] font-medium text-slate-700">{label}</span>}
      <div
        onClick={() => ref.current?.click()}
        onDragOver={(e) => { e.preventDefault(); setHover(true); }}
        onDragLeave={() => setHover(false)}
        onDrop={(e) => { e.preventDefault(); setHover(false); onFile?.(e.dataTransfer.files?.[0]); }}
        className={`flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed transition ${
          hover ? "border-[#2545b8] bg-[#2545b8]/5" : "border-slate-200 hover:border-slate-300 bg-slate-50/50"
        } ${tall ? "h-32" : "h-24"}`}
      >
        {previewUrl ? (
          <img src={previewUrl} alt="" className="h-full w-full rounded-xl object-cover" />
        ) : (
          <>
            <Upload size={18} className="text-slate-400" />
            <span className="mt-1.5 text-xs text-slate-500">Перетягніть або натисніть</span>
            {hint && <span className="text-[11px] text-slate-400">{hint}</span>}
          </>
        )}
        <input ref={ref} type="file" className="hidden" onChange={(e) => onFile?.(e.target.files?.[0])} />
      </div>
    </div>
  );
}

/* ---- KPI + charts (SECTION 13) ------------------------------------------- */
function Kpi({ label, value, delta, tone = "slate", sub }) {
  return (
    <div className="rounded-xl border border-slate-200/80 bg-white p-4">
      <div className="text-xs text-slate-500">{label}</div>
      <div className="mt-1 flex items-baseline gap-2">
        <span className="font-display text-2xl tnum text-[#14265e]" style={{ fontWeight: 600 }}>{value}</span>
        {delta && (
          <span className={`text-xs font-medium ${tone === "green" ? "text-emerald-600" : tone === "red" ? "text-red-500" : "text-slate-400"}`}>{delta}</span>
        )}
      </div>
      {sub && <div className="mt-0.5 text-[11px] text-slate-400">{sub}</div>}
    </div>
  );
}

function Bars() {
  const data = [40, 65, 52, 78, 90, 72, 110, 95, 130, 120, 145, 138];
  const labels = ["С","Л","Б","К","Т","Ч","Л","С","В","Ж","Л","Г"];
  const max = Math.max(...data);
  return (
    <div className="flex h-40 items-end gap-1.5">
      {data.map((v, i) => (
        <div key={i} className="group flex flex-1 flex-col items-center gap-1">
          <div className="relative w-full">
            <div
              className="w-full rounded-t-md bg-gradient-to-t from-[#2545b8] to-[#4d6fd8] transition-all group-hover:from-[#14265e] group-hover:to-[#2545b8]"
              style={{ height: `${(v / max) * 130}px` }}
            />
          </div>
          <span className="text-[10px] text-slate-400">{labels[i]}</span>
        </div>
      ))}
    </div>
  );
}

function Donut({ pct, label, color = "#2545b8" }) {
  const r = 34, c = 2 * Math.PI * r, off = c - (pct / 100) * c;
  return (
    <div className="flex flex-col items-center">
      <svg width="88" height="88" viewBox="0 0 88 88" className="-rotate-90">
        <circle cx="44" cy="44" r={r} fill="none" stroke="#eef1f7" strokeWidth="9" />
        <circle cx="44" cy="44" r={r} fill="none" stroke={color} strokeWidth="9" strokeLinecap="round" strokeDasharray={c} strokeDashoffset={off} className="transition-all duration-700" />
      </svg>
      <div className="-mt-[58px] mb-[26px] font-display text-xl tnum text-[#14265e]" style={{ fontWeight: 600 }}>{pct}%</div>
      <span className="text-xs text-slate-500">{label}</span>
    </div>
  );
}

function Progress({ pct, tone = "blue" }) {
  const c = tone === "green" ? "bg-emerald-500" : tone === "amber" ? "bg-amber-500" : "bg-[#2545b8]";
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-slate-100">
      <div className={`h-full rounded-full ${c} transition-all duration-700`} style={{ width: `${pct}%` }} />
    </div>
  );
}

/* ---- toast ---------------------------------------------------------------- */
function Toast({ show, text }) {
  if (!show) return null;
  return (
    <div className="lp-pop fixed bottom-6 left-1/2 z-50 -translate-x-1/2">
      <div className="flex items-center gap-2 rounded-xl bg-[#14265e] px-4 py-2.5 text-sm font-medium text-white shadow-lg">
        <span className="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-400/90"><Check size={13} className="text-[#14265e]" /></span>
        {text}
      </div>
    </div>
  );
}

/* ========================================================================== */
export default function LawyerProfileManager() {
  const [d, setD] = useState(INITIAL);
  const [active, setActive] = useState("profile");
  const [toast, setToast] = useState({ show: false, text: "" });
  const [saving, setSaving] = useState(false);
  const [lastSaved, setLastSaved] = useState("щойно");
  const set = (k, patch) => setD((s) => ({ ...s, [k]: { ...s[k], ...patch } }));

  const notify = useCallback((text) => {
    setToast({ show: true, text });
    setTimeout(() => setToast({ show: false, text: "" }), 2200);
  }, []);

  const save = (label = "Зміни збережено") => {
    setSaving(true);
    setTimeout(() => { setSaving(false); setLastSaved("щойно"); notify(label); }, 650);
  };

  /* autosave indicator ticker */
  useEffect(() => {
    const t = setInterval(() => setLastSaved((v) => (v === "щойно" ? "1 хв тому" : v)), 60000);
    return () => clearInterval(t);
  }, []);

  /* scrollspy */
  useEffect(() => {
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => { if (e.isIntersecting) setActive(e.target.id); });
      },
      { rootMargin: "-20% 0px -70% 0px", threshold: 0 }
    );
    NAV.forEach((n) => { const el = document.getElementById(n.id); if (el) obs.observe(el); });
    return () => obs.disconnect();
  }, []);

  /* completeness — derived, drives the signature nav dots */
  const completeness = useMemo(() => {
    const checks = [
      !!d.profile.fullName, !!d.profile.headline, d.profile.summary.length > 40,
      d.profile.languages.length > 0, !!d.profile.website,
      !!d.contacts.phone, !!d.contacts.email, !!d.contacts.city,
      !!d.legal.barNumber, d.legal.verifiedDocs,
      d.locations.length > 0, d.practice.selected.length > 0, !!d.practice.primary,
      d.services.length > 0, d.cases.length > 0, d.documents.length > 0,
      d.publications.length > 0, !!d.seo.metaTitle, !!d.seo.metaDescription,
    ];
    return Math.round((checks.filter(Boolean).length / checks.length) * 100);
  }, [d]);

  const sectionDone = useMemo(() => ({
    profile: !!d.profile.fullName && d.profile.summary.length > 40,
    contacts: !!d.contacts.phone && !!d.contacts.email,
    legal: !!d.legal.barNumber && d.legal.verifiedDocs,
    locations: d.locations.length > 0,
    practice: d.practice.selected.length > 0 && !!d.practice.primary,
    services: d.services.length > 0,
    publications: d.publications.length > 0,
    cases: d.cases.length > 0,
    documents: d.documents.length > 0,
    reviews: true, stats: true, subscription: true,
    seo: !!d.seo.metaTitle && !!d.seo.metaDescription,
    notifications: true, privacy: true,
  }), [d]);

  const goTo = (id) => document.getElementById(id)?.scrollIntoView({ behavior: "smooth", block: "start" });

  return (
    <div className="min-h-screen bg-[#f7f8fb] text-slate-800" style={{ fontFamily: "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif" }}>
      <style>{FONT_IMPORT}</style>

      {/* top bar with sticky save + autosave + completeness */}
      <header className="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl">
        <div className="mx-auto flex max-w-[1240px] items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <div className="flex items-center gap-3">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#14265e] text-white">
              <Scale size={18} />
            </div>
            <div>
              <div className="font-display text-[15px] leading-tight text-[#14265e]" style={{ fontWeight: 600 }}>Керування профілем</div>
              <div className="flex items-center gap-1.5 text-[11px] text-slate-400">
                <span className={`inline-block h-1.5 w-1.5 rounded-full ${saving ? "bg-amber-400" : "bg-emerald-400"}`} />
                {saving ? "Збереження…" : `Автозбереження · ${lastSaved}`}
              </div>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="hidden items-center gap-2.5 sm:flex">
              <div className="w-40">
                <div className="mb-1 flex justify-between text-[11px] text-slate-500">
                  <span>Профіль заповнено</span>
                  <span className="tnum font-medium text-[#14265e]">{completeness}%</span>
                </div>
                <Progress pct={completeness} tone={completeness >= 80 ? "green" : "amber"} />
              </div>
            </div>
            <a href="/lawyers/ihor-shevchenko" className="hidden items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 md:inline-flex">
              <Eye size={15} /> Переглянути
            </a>
            <button onClick={() => save("Усі зміни збережено")} className="inline-flex items-center gap-1.5 rounded-lg bg-[#14265e] px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-[#1d347e]">
              <Check size={15} /> Зберегти все
            </button>
          </div>
        </div>
      </header>

      <div className="mx-auto flex max-w-[1240px] gap-6 px-4 py-6 sm:px-6">
        {/* ---- sticky nav (signature: table of contents with status dots) ---- */}
        <aside className="sticky top-[73px] hidden h-[calc(100vh-89px)] w-60 shrink-0 lg:block">
          <nav className="lp-scroll h-full overflow-y-auto pr-2">
            <div className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">Розділи</div>
            <ul className="space-y-0.5">
              {NAV.map((n) => {
                const Icon = n.icon;
                const on = active === n.id;
                const done = sectionDone[n.id];
                return (
                  <li key={n.id}>
                    <button
                      onClick={() => goTo(n.id)}
                      className={`group flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition ${
                        on ? "bg-[#14265e]/5 font-medium text-[#14265e]" : "text-slate-600 hover:bg-slate-100"
                      }`}
                    >
                      <Icon size={16} className={on ? "text-[#14265e]" : "text-slate-400 group-hover:text-slate-500"} />
                      <span className="flex-1 text-left">{n.label}</span>
                      <span
                        className={`h-1.5 w-1.5 rounded-full ${done ? "bg-emerald-400" : "bg-amber-300"}`}
                        title={done ? "Заповнено" : "Потребує уваги"}
                      />
                    </button>
                  </li>
                );
              })}
            </ul>
          </nav>
        </aside>

        {/* ---- main content ---- */}
        <main className="min-w-0 flex-1 space-y-6">
          {/* SECTION 1 — PROFILE */}
          <Card id="profile" title="Профіль" description="Основна інформація, яку бачитимуть клієнти" icon={User} onSave={() => save()}>
            <Field label="Тип профілю">
              <Segmented
                value={d.profile.type}
                onChange={(v) => set("profile", { type: v })}
                options={[
                  { value: "lawyer", label: "Адвокат" },
                  { value: "firm", label: "Юридична фірма" },
                  { value: "bureau", label: "Адвокатське бюро" },
                  { value: "association", label: "Об'єднання" },
                ]}
              />
            </Field>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
              <UploadBox label="Аватар" hint="JPG/PNG, до 5 МБ" />
              <UploadBox label="Обкладинка" hint="1600×400" />
              <UploadBox label="Логотип компанії" hint="Прозорий PNG" />
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-2">
              <Field label="Повне ім'я"><Input value={d.profile.fullName} onChange={(e) => set("profile", { fullName: e.target.value })} /></Field>
              <Field label="Назва компанії"><Input value={d.profile.companyName} onChange={(e) => set("profile", { companyName: e.target.value })} /></Field>
            </div>

            <div className="mt-4">
              <Field label="Короткий заголовок" hint={`${d.profile.headline.length}/120`} error={d.profile.headline.length > 120 ? "Максимум 120 символів" : null}>
                <Input value={d.profile.headline} maxLength={140} onChange={(e) => set("profile", { headline: e.target.value })} />
              </Field>
            </div>

            <div className="mt-4">
              <Field label="Професійний опис" hint="Підтримується форматування">
                <div className="rounded-lg border border-slate-200 focus-within:border-[#2545b8] focus-within:ring-4 focus-within:ring-[#2545b8]/10">
                  <div className="flex items-center gap-1 border-b border-slate-100 px-2 py-1.5">
                    {["B", "I", "U"].map((b) => (
                      <button key={b} className="h-7 w-7 rounded text-sm font-semibold text-slate-500 hover:bg-slate-100">{b}</button>
                    ))}
                    <div className="mx-1 h-4 w-px bg-slate-200" />
                    <button className="h-7 w-7 rounded text-slate-500 hover:bg-slate-100">•</button>
                    <button className="h-7 rounded px-2 text-xs text-slate-500 hover:bg-slate-100">H2</button>
                  </div>
                  <textarea
                    rows={4}
                    value={d.profile.summary}
                    onChange={(e) => set("profile", { summary: e.target.value })}
                    className="w-full resize-none rounded-b-lg px-3 py-2 text-sm text-slate-800 focus:outline-none"
                  />
                </div>
              </Field>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <Field label="Мови">
                <div className="flex flex-wrap gap-1.5 rounded-lg border border-slate-200 p-2">
                  {d.languagesAll.map((l) => {
                    const on = d.profile.languages.includes(l);
                    return (
                      <button
                        key={l}
                        onClick={() => set("profile", { languages: on ? d.profile.languages.filter((x) => x !== l) : [...d.profile.languages, l] })}
                        className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${on ? "bg-[#14265e] text-white" : "bg-slate-100 text-slate-600 hover:bg-slate-200"}`}
                      >
                        {l}
                      </button>
                    );
                  })}
                </div>
              </Field>
              <Field label="Років досвіду"><Input type="number" value={d.profile.experience} onChange={(e) => set("profile", { experience: +e.target.value })} /></Field>
            </div>

            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <Field label="Вебсайт"><Input value={d.profile.website} onChange={(e) => set("profile", { website: e.target.value })} placeholder="https://" /></Field>
              <Field label="Відео-презентація (URL)"><Input value={d.profile.videoUrl} onChange={(e) => set("profile", { videoUrl: e.target.value })} placeholder="YouTube / Vimeo" /></Field>
            </div>

            <div className="mt-4">
              <span className="mb-1.5 block text-[13px] font-medium text-slate-700">Галерея</span>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {[1, 2].map((i) => (
                  <div key={i} className="relative flex h-24 items-center justify-center rounded-xl bg-slate-100 text-slate-300">
                    <ImageIcon size={20} />
                    <button className="absolute right-1.5 top-1.5 rounded-md bg-white/90 p-1 text-slate-400 hover:text-red-500"><X size={12} /></button>
                  </div>
                ))}
                <UploadBox />
              </div>
            </div>
          </Card>

          {/* SECTION 2 — CONTACTS */}
          <Card id="contacts" title="Контакти" description="Способи зв'язку та розташування" icon={Phone} onSave={() => save()}>
            <div className="grid gap-4 sm:grid-cols-2">
              {[
                ["phone", "Телефон"], ["email", "Email"], ["telegram", "Telegram"], ["whatsapp", "WhatsApp"],
                ["signal", "Signal"], ["viber", "Viber"], ["facebook", "Facebook"], ["linkedin", "LinkedIn"],
                ["instagram", "Instagram"], ["youtube", "YouTube"],
              ].map(([k, label]) => (
                <Field key={k} label={label}>
                  <Input value={d.contacts[k]} onChange={(e) => set("contacts", { [k]: e.target.value })} placeholder={k === "signal" || k === "youtube" ? "Не вказано" : ""} />
                </Field>
              ))}
            </div>
            <div className="my-6 h-px bg-slate-100" />
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2"><Field label="Адреса офісу"><Input value={d.contacts.address} onChange={(e) => set("contacts", { address: e.target.value })} /></Field></div>
              <Field label="Місто"><Input value={d.contacts.city} onChange={(e) => set("contacts", { city: e.target.value })} /></Field>
              <Field label="Область"><Input value={d.contacts.region} onChange={(e) => set("contacts", { region: e.target.value })} /></Field>
              <Field label="Країна"><Input value={d.contacts.country} onChange={(e) => set("contacts", { country: e.target.value })} /></Field>
              <Field label="Локація на Google Maps"><Input placeholder="Вставте посилання" /></Field>
            </div>
            <div className="mt-4 flex h-32 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-400">
              <MapPin size={16} className="mr-1.5" /> Попередній перегляд карти
            </div>
          </Card>

          {/* SECTION 3 — LEGAL */}
          <Card id="legal" title="Юридична інформація" description="Дані свідоцтва та реєстраційна інформація" icon={Scale} onSave={() => save()}>
            <div className="mb-5 flex flex-wrap gap-2">
              <Badge tone={d.legal.verifiedPhone ? "green" : "slate"}><ShieldCheck size={12} /> Телефон {d.legal.verifiedPhone ? "підтверджено" : "не підтверджено"}</Badge>
              <Badge tone={d.legal.verifiedEmail ? "green" : "slate"}><ShieldCheck size={12} /> Email {d.legal.verifiedEmail ? "підтверджено" : "не підтверджено"}</Badge>
              <Badge tone={d.legal.verifiedDocs ? "green" : "amber"}><Award size={12} /> Документи {d.legal.verifiedDocs ? "перевірено" : "на перевірці"}</Badge>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Номер свідоцтва адвоката"><Input value={d.legal.barNumber} onChange={(e) => set("legal", { barNumber: e.target.value })} /></Field>
              <Field label="Дата видачі"><Input type="date" value={d.legal.dateIssued} onChange={(e) => set("legal", { dateIssued: e.target.value })} /></Field>
              <Field label="Орган видачі"><Input value={d.legal.authority} onChange={(e) => set("legal", { authority: e.target.value })} /></Field>
              <Field label="Регіон реєстрації"><Input value={d.legal.region} onChange={(e) => set("legal", { region: e.target.value })} /></Field>
              <Field label="ЄДРПОУ"><Input value={d.legal.edrpou} onChange={(e) => set("legal", { edrpou: e.target.value })} /></Field>
              <Field label="Організаційна форма"><Input value={d.legal.legalForm} onChange={(e) => set("legal", { legalForm: e.target.value })} /></Field>
              <Field label="ІПН / ПДВ"><Input value={d.legal.vat} onChange={(e) => set("legal", { vat: e.target.value })} /></Field>
              <Field label="Посилання на реєстр адвокатів">
                <div className="flex gap-2">
                  <Input value={d.legal.registryUrl} onChange={(e) => set("legal", { registryUrl: e.target.value })} />
                  <a href={d.legal.registryUrl} className="flex shrink-0 items-center rounded-lg border border-slate-200 px-2.5 text-slate-500 hover:bg-slate-50"><ExternalLink size={15} /></a>
                </div>
              </Field>
            </div>
          </Card>

          {/* SECTION 4 — LOCATIONS */}
          <Card id="locations" title="Офіси" description="Кілька офісів із графіком роботи" icon={MapPin} onSave={() => save()}>
            <div className="space-y-4">
              {d.locations.map((loc) => (
                <div key={loc.id} className="rounded-xl border border-slate-200 p-4">
                  <div className="mb-3 flex items-center justify-between">
                    <input value={loc.name} onChange={(e) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, name: e.target.value } : l) }))} className="font-display text-base text-[#14265e] focus:outline-none" style={{ fontWeight: 600 }} />
                    <button onClick={() => setD((s) => ({ ...s, locations: s.locations.filter((l) => l.id !== loc.id) }))} className="text-slate-300 hover:text-red-500"><Trash2 size={16} /></button>
                  </div>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Адреса"><Input value={loc.address} onChange={(e) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, address: e.target.value } : l) }))} /></Field>
                    <Field label="Місто"><Input value={loc.city} onChange={(e) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, city: e.target.value } : l) }))} /></Field>
                    <Field label="Координати"><Input value={loc.coords} onChange={(e) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, coords: e.target.value } : l) }))} /></Field>
                    <Field label="Години роботи"><Input value={loc.hours} onChange={(e) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, hours: e.target.value } : l) }))} /></Field>
                  </div>
                  <div className="mt-3 flex flex-wrap gap-5">
                    <label className="flex items-center gap-2 text-sm text-slate-600"><Switch checked={loc.online} onChange={(v) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, online: v } : l) }))} label="Онлайн" /> Онлайн-консультації</label>
                    <label className="flex items-center gap-2 text-sm text-slate-600"><Switch checked={loc.appointment} onChange={(v) => setD((s) => ({ ...s, locations: s.locations.map((l) => l.id === loc.id ? { ...l, appointment: v } : l) }))} label="Запис" /> Візити за записом</label>
                  </div>
                </div>
              ))}
            </div>
            <button onClick={() => setD((s) => ({ ...s, locations: [...s.locations, { id: Date.now(), name: "Новий офіс", address: "", city: "", country: "Україна", coords: "", hours: "Пн–Пт 09:00–18:00", online: true, appointment: true }] }))} className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 hover:border-[#2545b8] hover:text-[#2545b8]">
              <Plus size={15} /> Додати офіс
            </button>
          </Card>

          {/* SECTION 5 — PRACTICE AREAS */}
          <Card id="practice" title="Галузі права" description="Оберіть спеціалізації та основну галузь" icon={Tag} onSave={() => save()}>
            <PracticePicker d={d} setD={setD} />
          </Card>

          {/* SECTION 6 + 7 + 8 — SERVICES / FREE / PRICING */}
          <Card id="services" title="Послуги та ціни" description="Прайс-лист, безкоштовні послуги та тарифікація" icon={Briefcase} onSave={() => save()}>
            <div className="mb-6 overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-left text-xs font-medium uppercase tracking-wide text-slate-400">
                    <th className="pb-2 pr-3">Послуга</th><th className="pb-2 pr-3">Ціна</th><th className="pb-2 pr-3">Тривалість</th>
                    <th className="pb-2 pr-3 text-center">Онлайн</th><th className="pb-2 pr-3 text-center">Офлайн</th>
                    <th className="pb-2 pr-3 text-center">Видима</th><th className="pb-2"></th>
                  </tr>
                </thead>
                <tbody>
                  {d.services.map((s) => (
                    <tr key={s.id} className="group border-b border-slate-50 hover:bg-slate-50/60">
                      <td className="py-2.5 pr-3">
                        <div className="font-medium text-slate-800">{s.name}</div>
                        <div className="text-xs text-slate-400">{s.desc}</div>
                      </td>
                      <td className="py-2.5 pr-3 tnum whitespace-nowrap">{s.price.toLocaleString("uk-UA")} {s.currency}</td>
                      <td className="py-2.5 pr-3 whitespace-nowrap text-slate-500">{s.duration}</td>
                      <td className="py-2.5 pr-3 text-center">{s.online ? <Check size={15} className="mx-auto text-emerald-500" /> : <span className="text-slate-300">—</span>}</td>
                      <td className="py-2.5 pr-3 text-center">{s.offline ? <Check size={15} className="mx-auto text-emerald-500" /> : <span className="text-slate-300">—</span>}</td>
                      <td className="py-2.5 pr-3 text-center">
                        <Switch checked={s.visible} onChange={(v) => setD((st) => ({ ...st, services: st.services.map((x) => x.id === s.id ? { ...x, visible: v } : x) }))} label="Видимість" />
                      </td>
                      <td className="py-2.5 text-right"><button onClick={() => setD((st) => ({ ...st, services: st.services.filter((x) => x.id !== s.id) }))} className="text-slate-300 opacity-0 transition group-hover:opacity-100 hover:text-red-500"><Trash2 size={15} /></button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <button onClick={() => setD((s) => ({ ...s, services: [...s.services, { id: Date.now(), name: "Нова послуга", desc: "", price: 0, currency: "UAH", duration: "1 год", online: true, offline: true, visible: true }] }))} className="inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 hover:border-[#2545b8] hover:text-[#2545b8]">
              <Plus size={15} /> Додати послугу
            </button>

            <div className="my-6 h-px bg-slate-100" />
            <div className="mb-2 text-[13px] font-semibold text-slate-700">Безкоштовні послуги</div>
            <div className="grid gap-2.5 sm:grid-cols-2">
              {[["consultation", "Безкоштовна консультація"], ["questions", "Відповіді на запитання"], ["docReview", "Безкоштовна перевірка документа"], ["assessment", "Первинна оцінка справи"]].map(([k, label]) => (
                <label key={k} className="flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                  <Switch checked={d.free[k]} onChange={(v) => set("free", { [k]: v })} label={label} /> {label}
                </label>
              ))}
            </div>

            <div className="my-6 h-px bg-slate-100" />
            <div className="mb-2 text-[13px] font-semibold text-slate-700">Тарифікація</div>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Погодинна ставка (грн)"><Input type="number" value={d.pricing.hourly} onChange={(e) => set("pricing", { hourly: +e.target.value })} /></Field>
              <Field label="Мінімальна вартість проєкту (грн)"><Input type="number" value={d.pricing.minProject} onChange={(e) => set("pricing", { minProject: +e.target.value })} /></Field>
              <label className="flex items-center gap-3 text-sm text-slate-700"><Switch checked={d.pricing.fixed} onChange={(v) => set("pricing", { fixed: v })} label="Фікс" /> Доступна фіксована ціна</label>
              <label className="flex items-center gap-3 text-sm text-slate-700"><Switch checked={d.pricing.successFee} onChange={(v) => set("pricing", { successFee: v })} label="Гонорар успіху" /> Гонорар успіху</label>
            </div>
            <div className="mt-4">
              <span className="mb-1.5 block text-[13px] font-medium text-slate-700">Способи оплати</span>
              <div className="flex flex-wrap gap-1.5">
                {["Картка", "Безготівковий", "LiqPay", "Готівка", "Криптовалюта"].map((p) => {
                  const on = d.pricing.payments.includes(p);
                  return <button key={p} onClick={() => set("pricing", { payments: on ? d.pricing.payments.filter((x) => x !== p) : [...d.pricing.payments, p] })} className={`rounded-md px-3 py-1.5 text-xs font-medium transition ${on ? "bg-[#14265e] text-white" : "bg-slate-100 text-slate-600 hover:bg-slate-200"}`}>{p}</button>;
                })}
              </div>
            </div>
          </Card>

          {/* SECTION 11 — PUBLICATIONS */}
          <Card id="publications" title="Публікації" description="Статті, гайди, новини та відео" icon={Newspaper} onSave={() => save()} footer={false}>
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-sm">
                <thead>
                  <tr className="border-b border-slate-100 text-left text-xs font-medium uppercase tracking-wide text-slate-400">
                    <th className="pb-2 pr-3">Заголовок</th><th className="pb-2 pr-3">Тип</th><th className="pb-2 pr-3">Опубліковано</th>
                    <th className="pb-2 pr-3 text-right">Перегляди</th><th className="pb-2 pr-3 text-right">Вподобання</th><th className="pb-2">Статус</th>
                  </tr>
                </thead>
                <tbody>
                  {d.publications.map((p) => (
                    <tr key={p.id} className="border-b border-slate-50 hover:bg-slate-50/60">
                      <td className="py-2.5 pr-3 font-medium text-slate-800">{p.title}</td>
                      <td className="py-2.5 pr-3"><Badge tone="blue">{p.type}</Badge></td>
                      <td className="py-2.5 pr-3 text-slate-500">{p.published}</td>
                      <td className="py-2.5 pr-3 text-right tnum text-slate-600">{p.views.toLocaleString("uk-UA")}</td>
                      <td className="py-2.5 pr-3 text-right tnum text-slate-600">{p.likes}</td>
                      <td className="py-2.5">{p.status === "published" ? <Badge tone="green">Опубліковано</Badge> : <Badge tone="amber">На модерації</Badge>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="mt-5 flex justify-end border-t border-slate-100 pt-4">
              <a href="/cabinet/articles/new" className="inline-flex items-center gap-1.5 rounded-lg bg-[#14265e] px-4 py-2 text-sm font-medium text-white hover:bg-[#1d347e]"><Plus size={15} /> Створити статтю</a>
            </div>
          </Card>

          {/* SECTION 9 — COURT CASES */}
          <Card id="cases" title="Судові справи" description="Портфоліо виграних та ведених справ" icon={Gavel} onSave={() => save()} footer={false}>
            <div className="space-y-3">
              {d.cases.map((c) => (
                <div key={c.id} className="flex items-start gap-4 rounded-xl border border-slate-200 p-4">
                  <div className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${c.result === "Виграно" ? "bg-emerald-50 text-emerald-600" : "bg-amber-50 text-amber-600"}`}>
                    <Gavel size={16} />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <span className="font-medium text-slate-800">{c.title}</span>
                      {!c.visible && <Badge tone="slate"><EyeOff size={11} /> Прихована</Badge>}
                    </div>
                    <div className="mt-0.5 text-xs text-slate-400">{c.category} · {c.year}</div>
                    <p className="mt-1 text-sm text-slate-500">{c.desc}</p>
                  </div>
                  <div className="flex flex-col items-end gap-2">
                    <Badge tone={c.result === "Виграно" ? "green" : "amber"}>{c.result}</Badge>
                    <div className="flex gap-1">
                      <button onClick={() => setD((s) => ({ ...s, cases: s.cases.map((x) => x.id === c.id ? { ...x, visible: !x.visible } : x) }))} className="rounded p-1 text-slate-300 hover:text-slate-600">{c.visible ? <Eye size={15} /> : <EyeOff size={15} />}</button>
                      <button onClick={() => setD((s) => ({ ...s, cases: s.cases.filter((x) => x.id !== c.id) }))} className="rounded p-1 text-slate-300 hover:text-red-500"><Trash2 size={15} /></button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <button onClick={() => setD((s) => ({ ...s, cases: [...s.cases, { id: Date.now(), title: "Нова справа", category: "Цивільне право", year: 2026, result: "Виграно", desc: "", visible: true }] }))} className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-3 py-2 text-sm font-medium text-slate-500 hover:border-[#2545b8] hover:text-[#2545b8]">
              <Plus size={15} /> Додати справу
            </button>
          </Card>

          {/* SECTION 10 — DOCUMENTS */}
          <Card id="documents" title="Документи" description="Дипломи, сертифікати, свідоцтва та нагороди" icon={FileText} onSave={() => save()} footer={false}>
            <UploadBox tall label={null} hint="PDF, JPG, PNG до 10 МБ" />
            <div className="mt-4 space-y-2">
              {d.documents.map((doc) => (
                <div key={doc.id} className="flex items-center gap-3 rounded-lg border border-slate-200 px-3 py-2.5">
                  <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 text-red-500"><FileText size={16} /></span>
                  <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-medium text-slate-800">{doc.name}</div>
                    <div className="text-xs text-slate-400">{doc.type} · {doc.size}</div>
                  </div>
                  {doc.status === "approved" ? <Badge tone="green"><ShieldCheck size={11} /> Перевірено</Badge> : <Badge tone="amber">На перевірці</Badge>}
                  <button onClick={() => setD((s) => ({ ...s, documents: s.documents.filter((x) => x.id !== doc.id) }))} className="text-slate-300 hover:text-red-500"><Trash2 size={15} /></button>
                </div>
              ))}
            </div>
          </Card>

          {/* SECTION 12 — REVIEWS */}
          <Card id="reviews" title="Відгуки" description="Керуйте відгуками клієнтів" icon={Star} onSave={() => save()} footer={false}>
            <ReviewsBlock d={d} setD={setD} notify={notify} />
          </Card>

          {/* SECTION 13 — STATISTICS */}
          <StatsSection />

          {/* SECTION 14 — SUBSCRIPTION */}
          <Card id="subscription" title="Підписка" description="Тарифний план і можливості" icon={CreditCard} onSave={() => save()} footer={false}>
            <SubscriptionBlock d={d} setD={setD} />
          </Card>

          {/* SECTION 15 — SEO */}
          <Card id="seo" title="SEO" description="Керуйте тим, як профіль виглядає у пошуку" icon={Search} onSave={() => save()}>
            <div className="grid gap-4 sm:grid-cols-2">
              <Field label="Slug (адреса сторінки)" hint="lexplatform.ua/lawyers/…">
                <div className="flex items-center rounded-lg border border-slate-200 focus-within:border-[#2545b8] focus-within:ring-4 focus-within:ring-[#2545b8]/10">
                  <span className="pl-3 text-sm text-slate-400">/lawyers/</span>
                  <input value={d.seo.slug} onChange={(e) => set("seo", { slug: e.target.value })} className="w-full rounded-r-lg px-1 py-2 text-sm focus:outline-none" />
                </div>
              </Field>
              <Field label="OpenGraph зображення"><UploadBox hint="1200×630" /></Field>
              <div className="sm:col-span-2"><Field label="Meta title" hint={`${d.seo.metaTitle.length}/60`}><Input value={d.seo.metaTitle} onChange={(e) => set("seo", { metaTitle: e.target.value })} /></Field></div>
              <div className="sm:col-span-2"><Field label="Meta description" hint={`${d.seo.metaDescription.length}/160`}>
                <textarea rows={2} value={d.seo.metaDescription} onChange={(e) => set("seo", { metaDescription: e.target.value })} className={`${inputCls} resize-none`} />
              </Field></div>
              <div className="sm:col-span-2"><Field label="Canonical URL"><Input value={d.seo.canonical} onChange={(e) => set("seo", { canonical: e.target.value })} /></Field></div>
            </div>
            <div className="mt-5">
              <span className="mb-2 block text-[13px] font-medium text-slate-700">Попередній перегляд у Google</span>
              <div className="rounded-xl border border-slate-200 p-4">
                <div className="text-xs text-slate-500">lexplatform.ua › lawyers › {d.seo.slug}</div>
                <div className="mt-0.5 text-lg text-[#1a0dab]">{d.seo.metaTitle}</div>
                <div className="mt-0.5 text-sm text-slate-600">{d.seo.metaDescription}</div>
              </div>
            </div>
          </Card>

          {/* SECTION 16 — NOTIFICATIONS */}
          <Card id="notifications" title="Сповіщення" description="Оберіть, про що вас повідомляти" icon={Bell} onSave={() => save()}>
            <div className="space-y-1">
              {[["email", "Email-сповіщення", "Нові відгуки, запити та повідомлення"], ["push", "Push-сповіщення", "У браузері та застосунку"], ["telegram", "Telegram-сповіщення", "Через бота LexPlatform"], ["weekly", "Щотижневий звіт", "Зведення активності профілю"], ["monthly", "Щомісячний звіт", "Детальна аналітика за місяць"]].map(([k, label, desc]) => (
                <div key={k} className="flex items-center justify-between rounded-lg px-3 py-3 hover:bg-slate-50">
                  <div><div className="text-sm font-medium text-slate-800">{label}</div><div className="text-xs text-slate-400">{desc}</div></div>
                  <Switch checked={d.notifications[k]} onChange={(v) => set("notifications", { [k]: v })} label={label} />
                </div>
              ))}
            </div>
          </Card>

          {/* SECTION 17 — PRIVACY */}
          <Card id="privacy" title="Приватність" description="Контролюйте видимість даних" icon={Lock} onSave={() => save()}>
            <Field label="Видимість профілю">
              <Segmented
                value={d.privacy.visibility}
                onChange={(v) => set("privacy", { visibility: v })}
                options={[{ value: "public", label: "Публічний" }, { value: "limited", label: "Обмежений" }, { value: "hidden", label: "Прихований" }]}
              />
            </Field>
            <div className="mt-5 space-y-1">
              {[["showPhone", "Показувати телефон"], ["showEmail", "Показувати email"], ["showPrices", "Показувати ціни"], ["allowMessages", "Дозволити повідомлення"], ["allowBooking", "Дозволити запис на консультацію"], ["allowReviews", "Дозволити публічні відгуки"]].map(([k, label]) => (
                <div key={k} className="flex items-center justify-between rounded-lg px-3 py-2.5 hover:bg-slate-50">
                  <span className="text-sm text-slate-700">{label}</span>
                  <Switch checked={d.privacy[k]} onChange={(v) => set("privacy", { [k]: v })} label={label} />
                </div>
              ))}
            </div>
          </Card>

          <div className="pb-10 pt-2 text-center text-xs text-slate-400">
            LexPlatform · Усі зміни зберігаються автоматично
          </div>
        </main>
      </div>

      <Toast show={toast.show} text={toast.text} />
    </div>
  );
}

/* ---- SECTION 5 body ------------------------------------------------------- */
function PracticePicker({ d, setD }) {
  const [q, setQ] = useState("");
  const filtered = d.practiceAll.filter((a) => a.toLowerCase().includes(q.toLowerCase()));
  const toggle = (a) => setD((s) => {
    const on = s.practice.selected.includes(a);
    const selected = on ? s.practice.selected.filter((x) => x !== a) : [...s.practice.selected, a];
    const primary = on && s.practice.primary === a ? (selected[0] || "") : s.practice.primary;
    return { ...s, practice: { ...s.practice, selected, primary } };
  });
  return (
    <div>
      <div className="relative mb-3">
        <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-300" />
        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Пошук галузі права…" className={`${inputCls} pl-9`} />
      </div>
      <div className="flex flex-wrap gap-1.5">
        {filtered.map((a) => {
          const on = d.practice.selected.includes(a);
          return (
            <button key={a} onClick={() => toggle(a)} className={`inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium transition ${on ? "bg-[#14265e] text-white" : "bg-slate-100 text-slate-600 hover:bg-slate-200"}`}>
              {on && <Check size={13} />}{a}
            </button>
          );
        })}
        {filtered.length === 0 && <div className="w-full py-6 text-center text-sm text-slate-400">Нічого не знайдено за запитом «{q}»</div>}
      </div>
      {d.practice.selected.length > 0 && (
        <div className="mt-5">
          <span className="mb-1.5 block text-[13px] font-medium text-slate-700">Основна спеціалізація</span>
          <div className="flex flex-wrap gap-1.5">
            {d.practice.selected.map((a) => (
              <button key={a} onClick={() => setD((s) => ({ ...s, practice: { ...s.practice, primary: a } }))} className={`inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm transition ${d.practice.primary === a ? "bg-amber-100 text-amber-800 ring-1 ring-amber-300" : "border border-slate-200 text-slate-500 hover:bg-slate-50"}`}>
                {d.practice.primary === a && <Star size={12} className="fill-amber-500 text-amber-500" />}{a}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

/* ---- SECTION 12 body ------------------------------------------------------ */
function ReviewsBlock({ d, setD, notify }) {
  const [filter, setFilter] = useState("all");
  const [replyTo, setReplyTo] = useState(null);
  const [replyText, setReplyText] = useState("");
  const avg = (d.reviews.reduce((a, r) => a + r.rating, 0) / d.reviews.length).toFixed(1);
  const list = d.reviews.filter((r) => filter === "all" ? true : filter === "positive" ? r.rating >= 4 : r.rating <= 3);

  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center gap-6 rounded-xl bg-slate-50 p-5">
        <div className="text-center">
          <div className="font-display text-4xl text-[#14265e]" style={{ fontWeight: 600 }}>{avg}</div>
          <div className="mt-1 flex justify-center gap-0.5">{[1, 2, 3, 4, 5].map((i) => <Star key={i} size={14} className={i <= Math.round(avg) ? "fill-amber-400 text-amber-400" : "text-slate-200"} />)}</div>
          <div className="mt-1 text-xs text-slate-400">{d.reviews.length} відгуків</div>
        </div>
        <div className="flex-1 space-y-1">
          {[5, 4, 3, 2, 1].map((star) => {
            const n = d.reviews.filter((r) => r.rating === star).length;
            return (
              <div key={star} className="flex items-center gap-2 text-xs text-slate-500">
                <span className="w-3 tnum">{star}</span><Star size={11} className="fill-amber-300 text-amber-300" />
                <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-amber-400" style={{ width: `${(n / d.reviews.length) * 100}%` }} /></div>
                <span className="w-4 tnum text-right">{n}</span>
              </div>
            );
          })}
        </div>
      </div>

      <div className="mb-3">
        <Segmented value={filter} onChange={setFilter} options={[{ value: "all", label: "Усі" }, { value: "positive", label: "Позитивні" }, { value: "negative", label: "Критичні" }]} />
      </div>

      <div className="space-y-3">
        {list.map((r) => (
          <div key={r.id} className={`rounded-xl border p-4 ${r.hidden ? "border-slate-200 bg-slate-50 opacity-60" : "border-slate-200"}`}>
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3">
                <div className="flex h-9 w-9 items-center justify-center rounded-full bg-[#14265e]/5 text-sm font-semibold text-[#14265e]">{r.author.slice(0, 1)}</div>
                <div>
                  <div className="text-sm font-medium text-slate-800">{r.author}</div>
                  <div className="flex items-center gap-1.5">
                    <div className="flex gap-0.5">{[1, 2, 3, 4, 5].map((i) => <Star key={i} size={11} className={i <= r.rating ? "fill-amber-400 text-amber-400" : "text-slate-200"} />)}</div>
                    <span className="text-xs text-slate-400">{r.date}</span>
                  </div>
                </div>
              </div>
              <div className="flex gap-1">
                <button onClick={() => { setReplyTo(r.id); setReplyText(r.reply || ""); }} className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100" title="Відповісти"><CornerUpLeft size={15} /></button>
                <button onClick={() => setD((s) => ({ ...s, reviews: s.reviews.map((x) => x.id === r.id ? { ...x, hidden: !x.hidden } : x) }))} className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100" title="Приховати">{r.hidden ? <Eye size={15} /> : <EyeOff size={15} />}</button>
                <button onClick={() => notify("Скаргу надіслано на модерацію")} className="rounded-md p-1.5 text-slate-400 hover:bg-slate-100" title="Поскаржитись"><Flag size={15} /></button>
              </div>
            </div>
            <p className="mt-2.5 text-sm text-slate-600">{r.text}</p>
            {r.reply && replyTo !== r.id && (
              <div className="mt-3 rounded-lg border-l-2 border-[#2545b8] bg-slate-50 px-3 py-2 text-sm">
                <div className="mb-0.5 text-xs font-medium text-[#2545b8]">Ваша відповідь</div>
                <div className="text-slate-600">{r.reply}</div>
              </div>
            )}
            {replyTo === r.id && (
              <div className="mt-3 lp-fade">
                <textarea value={replyText} onChange={(e) => setReplyText(e.target.value)} rows={2} placeholder="Напишіть відповідь…" className={`${inputCls} resize-none`} />
                <div className="mt-2 flex justify-end gap-2">
                  <button onClick={() => setReplyTo(null)} className="rounded-lg px-3 py-1.5 text-sm text-slate-500 hover:bg-slate-100">Скасувати</button>
                  <button onClick={() => { setD((s) => ({ ...s, reviews: s.reviews.map((x) => x.id === r.id ? { ...x, reply: replyText } : x) })); setReplyTo(null); notify("Відповідь опубліковано"); }} className="rounded-lg bg-[#14265e] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#1d347e]">Відповісти</button>
                </div>
              </div>
            )}
          </div>
        ))}
        {list.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-200 py-10 text-center">
            <Star size={22} className="mx-auto text-slate-300" />
            <div className="mt-2 text-sm font-medium text-slate-500">Немає відгуків у цій категорії</div>
            <div className="text-xs text-slate-400">Спробуйте інший фільтр</div>
          </div>
        )}
      </div>
    </div>
  );
}

/* ---- SECTION 13 body ------------------------------------------------------ */
function StatsSection() {
  const kpis = [
    { label: "Загальний рейтинг", value: "4.9", delta: "+0.2", tone: "green", sub: "з 5.0" },
    { label: "Місце в категорії", value: "#3", sub: "Кримінальні справи" },
    { label: "Місце в місті", value: "#7", sub: "Київ" },
    { label: "Перегляди профілю", value: "12.4K", delta: "+18%", tone: "green", sub: "за 30 днів" },
    { label: "Унікальні відвідувачі", value: "8 210", delta: "+12%", tone: "green" },
    { label: "Перегляди статей", value: "24.1K", delta: "+31%", tone: "green" },
    { label: "Кліки по телефону", value: "342", delta: "+9%", tone: "green" },
    { label: "Кліки по email", value: "128", sub: "за 30 днів" },
    { label: "Підписники", value: "1 204", delta: "+45", tone: "green" },
    { label: "Збережень профілю", value: "687" },
    { label: "Відповіді на питання", value: "156", sub: "89 прийнято" },
    { label: "Сер. час відповіді", value: "2.4 год", delta: "-0.6", tone: "green" },
  ];
  const secondary = [
    { label: "Консультації", value: "94" },
    { label: "Платні", value: "71" },
    { label: "Безкоштовні", value: "23" },
    { label: "Відео-консультації", value: "38" },
    { label: "Укладено договорів", value: "42" },
    { label: "Сер. чек", value: "8 400 ₴" },
    { label: "Конверсія", value: "6.8%" },
    { label: "Відгуків", value: "128" },
  ];
  return (
    <section id="stats" className="scroll-mt-24">
      <div className="lp-fade rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(16,24,40,.04)]">
        <div className="flex items-center gap-3 border-b border-slate-100 px-6 py-5">
          <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-[#14265e]/5 text-[#14265e]"><BarChart3 size={18} /></span>
          <div>
            <h2 className="font-display text-lg text-[#14265e]" style={{ fontWeight: 600 }}>Статистика</h2>
            <p className="text-sm text-slate-500">Аналітика ефективності профілю за останні 30 днів</p>
          </div>
        </div>

        <div className="p-6">
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {kpis.map((k) => <Kpi key={k.label} {...k} />)}
          </div>

          <div className="mt-4 grid gap-4 lg:grid-cols-3">
            <div className="rounded-xl border border-slate-200/80 bg-white p-5 lg:col-span-2">
              <div className="mb-4 flex items-center justify-between">
                <div className="text-sm font-medium text-slate-700">Перегляди профілю</div>
                <Badge tone="green"><TrendingUp size={11} /> +18% до попереднього періоду</Badge>
              </div>
              <Bars />
            </div>
            <div className="rounded-xl border border-slate-200/80 bg-white p-5">
              <div className="mb-4 text-sm font-medium text-slate-700">Показники якості</div>
              <div className="flex justify-around">
                <Donut pct={92} label="Заповнено" color="#2545b8" />
                <Donut pct={96} label="Відповіді" color="#059669" />
              </div>
            </div>
          </div>

          <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {secondary.map((s) => (
              <div key={s.label} className="rounded-xl bg-slate-50 p-4">
                <div className="text-xs text-slate-500">{s.label}</div>
                <div className="mt-1 font-display text-xl tnum text-[#14265e]" style={{ fontWeight: 600 }}>{s.value}</div>
              </div>
            ))}
          </div>

          <div className="mt-4 grid gap-4 sm:grid-cols-3">
            {[["Позитивні відгуки", 94, "green"], ["Рівень відповідей", 96, "blue"], ["Заповненість профілю", 92, "amber"]].map(([label, pct, tone]) => (
              <div key={label} className="rounded-xl border border-slate-200/80 p-4">
                <div className="mb-2 flex justify-between text-sm"><span className="text-slate-600">{label}</span><span className="tnum font-medium text-[#14265e]">{pct}%</span></div>
                <Progress pct={pct} tone={tone} />
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

/* ---- SECTION 14 body ------------------------------------------------------ */
function SubscriptionBlock({ d, setD }) {
  const plans = [
    { id: "free", name: "Free", price: "0 ₴", features: ["Базовий профіль", "До 3 послуг", "Стандартне розміщення"] },
    { id: "pro", name: "Pro", price: "990 ₴/міс", features: ["Необмежені послуги", "Розширена статистика", "Пріоритетне розміщення", "Значок Verified"] },
    { id: "business", name: "Business", price: "2 490 ₴/міс", features: ["Все з Pro", "Featured-профіль", "CRM для клієнтів", "Кілька офісів"] },
    { id: "enterprise", name: "Enterprise", price: "Індивідуально", features: ["Все з Business", "API-доступ", "Персональний менеджер", "White-label"] },
  ];
  return (
    <div>
      <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-[#14265e] to-[#2545b8] p-5 text-white">
        <div>
          <div className="text-xs text-white/70">Поточний план</div>
          <div className="font-display text-2xl" style={{ fontWeight: 600 }}>Pro</div>
          <div className="mt-0.5 text-xs text-white/70">Дійсний до {d.subscription.expires}</div>
        </div>
        <div className="flex items-center gap-2 text-sm">
          <Sparkles size={16} className="text-amber-300" />
          <span>Пріоритетне розміщення активне</span>
        </div>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {plans.map((p) => {
          const cur = d.subscription.plan === p.id;
          return (
            <div key={p.id} className={`rounded-xl border p-4 ${cur ? "border-[#2545b8] ring-1 ring-[#2545b8]/20" : "border-slate-200"}`}>
              <div className="flex items-center justify-between">
                <span className="font-display text-base text-[#14265e]" style={{ fontWeight: 600 }}>{p.name}</span>
                {cur && <Badge tone="blue">Активний</Badge>}
              </div>
              <div className="mt-1 text-sm font-medium text-slate-700">{p.price}</div>
              <ul className="mt-3 space-y-1.5">
                {p.features.map((f) => <li key={f} className="flex items-start gap-1.5 text-xs text-slate-500"><Check size={13} className="mt-0.5 shrink-0 text-emerald-500" />{f}</li>)}
              </ul>
              {!cur && (
                <button onClick={() => setD((s) => ({ ...s, subscription: { ...s.subscription, plan: p.id } }))} className="mt-4 w-full rounded-lg bg-[#14265e] py-2 text-sm font-medium text-white hover:bg-[#1d347e]">
                  {p.id === "free" ? "Перейти" : "Оновити"}
                </button>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}
