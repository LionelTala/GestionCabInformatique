import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';
import { tap } from 'rxjs/operators';

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  role: string;
  campus_id?: number;
  campus?: { id: number; name: string; city: string };
  is_active: boolean;
  created_at?: string;
}

interface Meta {
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

@Injectable({ providedIn: 'root' })
export class UserService {
  private apiUrl = environment.apiUrl;
  private users = signal<User[]>([]);
  private meta = signal<Meta>({ current_page: 1, last_page: 1, total: 0, per_page: 15 });

  constructor(private http: HttpClient) {}

  getUsers() { return this.users.asReadonly(); }
  getMeta() { return this.meta.asReadonly(); }

  loadUsers(page = 1, params?: Record<string, string>) {
    return this.http.get<any>(`${this.apiUrl}/users`, {
      params: { page, per_page: 15, ...params }
    }).pipe(
      tap(response => {
        this.users.set(response.data.data);
        this.meta.set(response.data);
      })
    );
  }

  create(data: Partial<User>) {
    return this.http.post(`${this.apiUrl}/users`, data);
  }

  update(id: number, data: Partial<User>) {
    return this.http.put(`${this.apiUrl}/users/${id}`, data);
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/users/${id}`);
  }

  toggleStatus(id: number) {
    return this.http.patch(`${this.apiUrl}/users/${id}/toggle-status`, {});
  }
}