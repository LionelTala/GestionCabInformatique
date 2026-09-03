import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class StudentService {
  private apiUrl = environment.apiUrl;
  private students = signal<any[]>([]);
  private meta = signal<any>({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
  });
  private loading = signal(false);

  constructor(private http: HttpClient) {}

  getStudents() { return this.students.asReadonly(); }
  getMeta() { return this.meta.asReadonly(); }
  getLoading() { return this.loading.asReadonly(); }

  loadStudents(page: number = 1, filters: any = {}) {
    this.loading.set(true);
    const params: any = { page, per_page: 15, ...filters };

    return this.http.get<any>(`${this.apiUrl}/students`, { params }).subscribe({
      next: (res) => {
        this.students.set(res.data.data);
        this.meta.set({
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          per_page: res.data.per_page,
          total: res.data.total,
          from: res.data.from,
          to: res.data.to,
        });
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  getStudent(id: number) {
    return this.http.get<any>(`${this.apiUrl}/students/${id}`);
  }

   updateStudent(id: number, data: FormData) {
    return this.http.post<any>(`${this.apiUrl}/students/${id}`, data);
  }
}