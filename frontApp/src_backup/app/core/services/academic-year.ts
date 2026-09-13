import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { tap } from 'rxjs/operators';

export interface AcademicYear {
  id: number;
  label: string;
  start_date: string;
  end_date: string;
  is_current: boolean;
  is_active: boolean;
}

@Injectable({ providedIn: 'root' })
export class AcademicYearService {
  private apiUrl = environment.apiUrl;
  private years = signal<AcademicYear[]>([]);
  private currentYearId = signal<number | null>(null);

  constructor(private http: HttpClient) {}

  getYears() { return this.years.asReadonly(); }
  getCurrentYearId() { return this.currentYearId.asReadonly(); }

  loadYears() {
    return this.http.get<any>(`${this.apiUrl}/academic-years`).pipe(
      tap(response => {
        this.years.set(response.data);
        this.currentYearId.set(response.current_year_id);
      })
    );
  }

  switchYear(yearId: number) {
    return this.http.patch(`${this.apiUrl}/academic-years/switch`, { academic_year_id: yearId }).pipe(
      tap(() => this.currentYearId.set(yearId))
    );
  }

  create(data: Partial<AcademicYear>) {
    return this.http.post<any>(`${this.apiUrl}/academic-years`, data).pipe(
      tap((res) => {
        // ✅ Recharge la liste après création
        this.loadYears().subscribe();
      })
    );
  }

  update(id: number, data: Partial<AcademicYear>) {
    // ✅ URL corrigée : pluriel
    return this.http.put<any>(`${this.apiUrl}/academic-years/${id}`, data).pipe(
      tap((res) => {
        // ✅ Met à jour localement sans recharger
        this.years.update(years =>
          years.map(y => y.id === id ? { ...y, ...res.data } : y)
        );
      })
    );
  }

  delete(id: number) {
    return this.http.delete<any>(`${this.apiUrl}/academic-years/${id}`).pipe(
      tap(() => {
        // ✅ Retire l'année de la liste locale
        this.years.update(years => years.filter(y => y.id !== id));
      })
    );
  }
}