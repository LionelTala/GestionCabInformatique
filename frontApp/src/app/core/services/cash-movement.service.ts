// src/app/core/services/cash-movement.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface CashMovementMeta {
  period: string;
  date_from: string | null;
  date_to: string | null;
  total_income: number;
  total_expense: number;
  balance: number;
  total_count: number;
}

export interface CashSummary {
  today:  { income: number; expense: number; balance: number };
  month:  { income: number; expense: number; balance: number };
  year:   { income: number; expense: number; balance: number };
  total:  { income: number; expense: number; balance: number };
}

@Injectable({ providedIn: 'root' })
export class CashMovementService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/cash-movements`;

  // ═══ LISTE ═══
  getMovements(page = 1, filters: any = {}) {
    const params: any = { page, per_page: 15, ...filters };
    return this.http.get<any>(this.apiUrl, { params });
  }

  // ═══ CRÉATION ═══
  createMovement(formData: FormData) {
    return this.http.post<any>(this.apiUrl, formData);
  }

  // ═══ SUPPRESSION ═══
  deleteMovement(id: number) {
    return this.http.delete<any>(`${this.apiUrl}/${id}`);
  }

  // ═══ PIÈCE JOINTE ═══
  downloadAttachment(id: number) {
    return this.http.get(`${this.apiUrl}/${id}/attachment`, { responseType: 'blob' });
  }

  // ═══ RÉSUMÉ (KPIs) ═══
  getSummary(params: any = {}) {
    return this.http.get<{ data: CashSummary }>(`${this.apiUrl}/summary`, { params });
  }

  // ═══ CATÉGORIES ═══
  getCategories() {
    return this.http.get<{ data: { income: Record<string, string>; expense: Record<string, string> } }>(
      `${this.apiUrl}/categories`
    );
  }
}