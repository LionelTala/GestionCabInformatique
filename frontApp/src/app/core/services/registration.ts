import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface LaravelPaginator<T> {
  current_page: number;
  data: T[];
  first_page_url: string;
  from: number;
  last_page: number;
  last_page_url: string;
  links: { url: string | null; label: string; active: boolean }[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number;
  total: number;
}

export interface RegistrationMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number;
  to: number;
}

@Injectable({
  providedIn: 'root'
})
export class RegistrationService {
  private apiUrl = environment.apiUrl;
  private registrations = signal<any[]>([]);
  private meta = signal<RegistrationMeta>({
    current_page: 1, last_page: 1, per_page: 15, total: 0, from: 0, to: 0,
  });
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getRegistrations() { return this.registrations.asReadonly(); }
  getMeta() { return this.meta.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  loadRegistrations(page: number = 1, filters?: any) {
    const params: any = { page, per_page: 15 };
    if (filters?.campus_id) params.campus_id = filters.campus_id;
    if (filters?.formation_id) params.formation_id = filters.formation_id;
    if (filters?.academic_year_id) params.academic_year_id = filters.academic_year_id;
    if (filters?.status) params.status = filters.status;

    return this.http.get<{ data: LaravelPaginator<any> }>(`${this.apiUrl}/registrations`, { params });
  }

  refresh(page: number = 1, filters?: any) {
    this.loading.set(true);
    this.loadRegistrations(page, filters).subscribe({
      next: (response) => {
        const paginator = response.data;
        this.registrations.set(paginator.data);
        this.meta.set({
          current_page: paginator.current_page, last_page: paginator.last_page,
          per_page: paginator.per_page, total: paginator.total,
          from: paginator.from, to: paginator.to,
        });
        this.loading.set(false);
      },
      error: () => { this.loading.set(false); }
    });
  }

  create(data: FormData) {
    return this.http.post<{ data: any }>(`${this.apiUrl}/registrations`, data);
  }

  downloadForm(id: number) {
    return this.http.get(`${this.apiUrl}/registrations/${id}/form`, { responseType: 'blob' });
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/registrations/${id}`);
  }
}