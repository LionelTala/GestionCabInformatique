import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface AcademicYear {
  id: number;
  label: string;
  start_date: string;
  end_date: string;
  is_current: boolean;
  is_active: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class AcademicYearService {
  private apiUrl = environment.apiUrl;
  private years = signal<AcademicYear[]>([]);

  constructor(private http: HttpClient) {}

  getYears() {
    return this.years.asReadonly();
  }

  loadYears() {
    return this.http.get<{ data: AcademicYear[]; current_year_id: number }>(
      `${this.apiUrl}/academic-years`
    );
  }

  refresh() {
    this.loadYears().subscribe({
      next: (response) => {
        this.years.set(response.data);
      },
      error: () => {
        // Géré par l'intercepteur ou le composant
      }
    });
  }

  switchYear(yearId: number) {
    return this.http.patch(`${this.apiUrl}/academic-years/switch`, { academic_year_id: yearId });
  }

  create(year: Partial<AcademicYear>) {
    return this.http.post<{ data: AcademicYear }>(`${this.apiUrl}/academic-years`, year);
  }

  update(id: number, year: Partial<AcademicYear>) {
    return this.http.put<{ data: AcademicYear }>(`${this.apiUrl}/academic-years/${id}`, year);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/academic-years/${id}`);
  }
}