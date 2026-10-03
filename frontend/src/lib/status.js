const tones = {
  active: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  approved: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  issued: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  yes: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  on_time: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  expiring: 'bg-amber-50 text-amber-800 ring-amber-200',
  deficiency_issued: 'bg-amber-50 text-amber-800 ring-amber-200',
  fee_pending: 'bg-amber-50 text-amber-800 ring-amber-200',
  under_review: 'bg-sky-50 text-sky-800 ring-sky-200',
  submitted: 'bg-sky-50 text-sky-800 ring-sky-200',
  open: 'bg-sky-50 text-sky-800 ring-sky-200',
  renewal: 'bg-sky-50 text-sky-800 ring-sky-200',
  ready_to_issue: 'bg-violet-50 text-violet-800 ring-violet-200',
  pending: 'bg-orange-50 text-orange-800 ring-orange-200',
  unlicensed: 'bg-slate-100 text-slate-700 ring-slate-200',
  inactive: 'bg-slate-100 text-slate-700 ring-slate-200',
  no: 'bg-slate-100 text-slate-700 ring-slate-200',
  not_applicable: 'bg-slate-100 text-slate-700 ring-slate-200',
  incomplete: 'bg-rose-50 text-rose-800 ring-rose-200',
  expired: 'bg-red-50 text-red-700 ring-red-200',
  suspended: 'bg-orange-50 text-orange-800 ring-orange-200',
  cancelled: 'bg-red-50 text-red-700 ring-red-200',
  rejected: 'bg-red-50 text-red-700 ring-red-200',
  withdrawn: 'bg-slate-100 text-slate-700 ring-slate-200',
  overdue: 'bg-red-50 text-red-700 ring-red-200',
  staff: 'bg-slate-100 text-slate-700 ring-slate-200',
  company: 'bg-violet-50 text-violet-800 ring-violet-200',
  dealer: 'bg-sky-50 text-sky-800 ring-sky-200',
  registration: 'bg-violet-50 text-violet-800 ring-violet-200',
  restoration: 'bg-orange-50 text-orange-800 ring-orange-200',
  complete: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  verified: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
  deficient: 'bg-amber-50 text-amber-800 ring-amber-200',
}

export function statusTone(value) {
  if (value === true || value === 1 || value === '1') {
    return tones.yes
  }

  if (value === false || value === 0 || value === '0') {
    return tones.no
  }

  return tones[String(value || '').toLowerCase()] || 'bg-muted text-muted-foreground ring-border'
}
