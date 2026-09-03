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

@Injectable({
  providedIn: 'root'
})
export class ActivityLogService {
  private apiUrl = environment.apiUrl;
  
  // logs est un tableau d'objets ActivityLog
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

  constructor(private http: HttpClient) {}

  getLogs() { return this.logs.asReadonly(); }
  getMeta() { return this.meta.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  // Restauration d'une inscription (utilisée dans le composant Logs)
  restore(id: number) {
    return this.http.post(`${this.apiUrl}/registrations/${id}/restore`, {});
  }

  loadLogs(page: number = 1, filters: any = {}) {
    this.loading.set(true);
    
    // ✅ URL corrigée vers le nouveau contrôleur dédié
    return this.http.get<any>(`${this.apiUrl}/activity-logs`, { 
      params: { page, ...filters } 
    }).subscribe({
      next: (res) => {
        // Laravel renvoie { data: [...], current_page: 1, total: 10, ... }
        this.logs.set(res.data);
        this.meta.set({
          current_page: res.current_page,
          from: res.from,
          last_page: res.last_page,
          per_page: res.per_page,
          to: res.to,
          total: res.total,
        });
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