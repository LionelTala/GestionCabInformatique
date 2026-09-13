// src/app/core/services/activity-log.ts
import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface ActivityLog {
  id: number;
  user_id: number;
  user_role: string;
  campus_id?: number;
  action: 'created' | 'updated' | 'deleted' | 'restored';
  target_type: string;
  target_id: number;
  target_name?: string;
  old_data?: any;
  new_data?: any;
  changes?: string;
  created_at: string;
  user?: {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
  };
  campus?: {
    id: number;
    name: string;
  };
}

export interface LogMeta {
  current_page: number;
  from: number;
  last_page: number;
  per_page: number;
  to: number;
  total: number;
}

export interface PeriodMeta {
  period: string;
  date_from: string | null;
  date_to: string | null;
  total_count: number;
}

@Injectable({
  providedIn: 'root'
})
export class ActivityLogService {
  private apiUrl = environment.apiUrl;

  private logs = signal<ActivityLog[]>([]);
  private meta = signal<LogMeta>({
    current_page: 1,
    from: 0,
    last_page: 1,
    per_page: 30,
    to: 0,
    total: 0,
  });
  private loading = signal(false);

  private periodMeta = signal<PeriodMeta>({
    period: 'today',
    date_from: null,
    date_to: null,
    total_count: 0,
  });

  constructor(private http: HttpClient) {}

  getLogs()       { return this.logs.asReadonly(); }
  getMeta()       { return this.meta.asReadonly(); }
  getLoading()    { return this.loading.asReadonly(); }
  getPeriodMeta() { return this.periodMeta.asReadonly(); }

  restore(id: number) {
    return this.http.post(`${this.apiUrl}/registrations/${id}/restore`, {});
  }

  loadLogs(page: number = 1, filters: any = {}) {
    this.loading.set(true);

    const params: any = {
      page,
      period: filters?.period || 'today',
      ...filters,
    };

    return this.http.get<any>(`${this.apiUrl}/activity-logs`, { params }).subscribe({
      next: (res) => {
        // ✅ Détection automatique du format :
        // - Format A (imbriqué) : { data: { data: [...], current_page, ... }, meta: {...} }
        // - Format B (brut)     : { data: [...], current_page, ... }
        const isNested = res?.data && Array.isArray(res.data.data);
        const logsArray = isNested ? res.data.data : (Array.isArray(res.data) ? res.data : []);
        const pagination = isNested ? res.data : res;

        this.logs.set(logsArray);
        this.meta.set({
          current_page: pagination.current_page ?? 1,
          from:         pagination.from         ?? 0,
          last_page:    pagination.last_page    ?? 1,
          per_page:     pagination.per_page     ?? 30,
          to:           pagination.to           ?? 0,
          total:        pagination.total        ?? 0,
        });

        // ✅ Meta période (si présent)
        if (res.meta) {
          this.periodMeta.set(res.meta);
        } else {
          // Fallback si le backend ne renvoie pas de meta
          this.periodMeta.set({
            period: filters?.period || 'today',
            date_from: null,
            date_to: null,
            total_count: pagination.total ?? 0,
          });
        }

        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
      }
    });
  }

  refresh(page: number = 1, filters?: any) {
    this.loadLogs(page, filters);
  }
}