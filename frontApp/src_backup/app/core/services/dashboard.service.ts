// src/app/core/services/dashboard.service.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export type DashboardPeriod = 'today' | 'week' | 'month' | 'year' | 'custom' | 'all';

@Injectable({ providedIn: 'root' })
export class DashboardService {
  private apiUrl = environment.apiUrl;
  private stats = signal<any>(null);
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getStats() { return this.stats.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  loadStats(params: {
    period?: DashboardPeriod;
    date_from?: string;
    date_to?: string;
    campus_id?: number | null;
  } = {}) {
    this.loading.set(true);
    const query: any = { period: params.period || 'today' };
    if (params.date_from) query.date_from = params.date_from;
    if (params.date_to) query.date_to = params.date_to;
    if (params.campus_id) query.campus_id = params.campus_id;

    this.http.get<any>(`${this.apiUrl}/dashboard/stats`, { params: query }).subscribe({
      next: (res) => {
        this.stats.set(res.data);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }
}