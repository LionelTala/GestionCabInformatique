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
    getScholarshipReport(filters: any = {}) {
    return this.http.get<any>(`${this.apiUrl}/students/scholarship-report`, { params: filters });
  }

  downloadScholarshipReport(filters: any = {}) {
    return this.http.get(`${this.apiUrl}/students/scholarship-report/pdf`, { 
      params: filters,
      responseType: 'blob' // ✅ Indique à Angular d'attendre un fichier binaire
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `rapport-scolarite-${new Date().getTime()}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      },
      error: (err) => {
        console.error('Erreur téléchargement PDF', err);
        // Si c'est une erreur 401, l'intercepteur auth te déconnectera automatiquement
      }
    });
  }

  downloadSimpleList(filters: any = {}) {
    return this.http.get(`${this.apiUrl}/students/simple-list/pdf`, { 
      params: filters,
      responseType: 'blob'
    }).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `liste-etudiants-${new Date().getTime()}.pdf`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
      },
      error: (err) => {
        console.error('Erreur téléchargement PDF', err);
      }
    });
  }

  getSimpleList(filters: any = {}) {
    return this.http.get<any>(`${this.apiUrl}/students/simple-list`, { params: filters });
  }

 
}