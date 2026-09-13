import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { tap } from 'rxjs/operators';

export interface Formation {
  id: number;
  name: string;
  abbreviation: string;
  tuition_fees: number;
  duration_months: number;
  is_active: boolean;
  created_at?: string;
}

@Injectable({ providedIn: 'root' })
export class FormationService {
  private apiUrl = environment.apiUrl;
  private formations = signal<Formation[]>([]);

  constructor(private http: HttpClient) {}

  getFormations() { return this.formations.asReadonly(); }

  loadFormations() {
    return this.http.get<{ data: Formation[] }>(`${this.apiUrl}/formations`).pipe(
      tap(response => this.formations.set(response.data))
    );
  }

  create(data: Partial<Formation>) {
    return this.http.post(`${this.apiUrl}/formations`, data);
  }

  update(id: number, data: Partial<Formation>) {
    return this.http.put(`${this.apiUrl}/formations/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/formations/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch(`${this.apiUrl}/formations/${id}/toggle-status`, {});
  }
}