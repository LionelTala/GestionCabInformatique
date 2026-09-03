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
    return this.http.post(`${this.apiUrl}/academic-years`, data);
  }

  update(id: number, data: Partial<AcademicYear>) {
    return this.http.put(`${this.apiUrl}/academic-year/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/academic-years/${id}`);
  }
}