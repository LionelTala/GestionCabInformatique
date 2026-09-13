// src/app/core/services/financial-movement.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface MovementSummary {
  period: { income: number; expense: number; balance: number };
  global: { income: number; expense: number; balance: number };
}

export interface MovementMeta {
  period: string;
  date_from: string | null;
  date_to: string | null;
  total_count: number;
}

@Injectable({ providedIn: 'root' })
export class FinancialMovementService {
  private apiUrl = environment.apiUrl;

  private movements = signal<any[]>([]);
  private meta = signal<any>({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: 0,
    to: 0,
  });
  private summary = signal<MovementSummary>({
    period: { income: 0, expense: 0, balance: 0 },
    global: { income: 0, expense: 0, balance: 0 },
  });
  private periodMeta = signal<MovementMeta>({
    period: 'today',
    date_from: null,
    date_to: null,
    total_count: 0,
  });
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getMovements()   { return this.movements.asReadonly(); }
  getMeta()        { return this.meta.asReadonly(); }
  getSummary()     { return this.summary.asReadonly(); }
  getPeriodMeta()  { return this.periodMeta.asReadonly(); }
  getLoading()     { return this.loading.asReadonly(); }

  loadMovements(page = 1, filters: any = {}) {
    this.loading.set(true);

    const params: any = {
      page,
      per_page: 20,
      period: filters?.period || 'today',
      ...filters,
    };

    this.http.get<any>(`${this.apiUrl}/financial-movements`, { params }).subscribe({
      next: (res) => {
        // ✅ Structure : res.data = paginate, res.summary = 2 soldes, res.meta = période
        this.movements.set(res.data?.data ?? res.data ?? []);
        this.meta.set({
          current_page: res.data.current_page ?? 1,
          last_page:    res.data.last_page ?? 1,
          total:        res.data.total ?? 0,
          from:         res.data.from ?? 0,
          to:           res.data.to ?? 0,
        });

        // ✅ 2 soldes
        if (res.summary) {
          this.summary.set(res.summary);
        }

        // ✅ Meta période
        if (res.meta) {
          this.periodMeta.set(res.meta);
        }

        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  generateReport(filters: any = {}) {
    const params = new URLSearchParams(filters).toString();
    window.open(`${this.apiUrl}/financial-movements/report?${params}`, '_blank');
  }
}