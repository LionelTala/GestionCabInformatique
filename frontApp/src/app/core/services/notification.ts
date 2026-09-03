import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

export interface Notification {
  id: number;
  user_id: number;
  type: string;
  title: string;
  message: string;
  link?: string;
  campus_id?: number;
  is_read: boolean;
  read_at?: string;
  created_at: string;
}

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private apiUrl = environment.apiUrl;
  private notifications = signal<Notification[]>([]);
  private unreadCount = signal(0);

  constructor(private http: HttpClient) {}

  getNotifications() {
    return this.notifications.asReadonly();
  }

  getUnreadCount() {
    return this.unreadCount.asReadonly();
  }

  loadNotifications() {
    this.http.get<{ data: Notification[] }>(`${this.apiUrl}/notifications`).subscribe({
      next: (response) => {
        this.notifications.set(response.data);
        this.unreadCount.set(response.data.filter(n => !n.is_read).length);
      },
      error: () => {}
    });
  }

  markAsRead(id: number) {
    // ⚡ Met à jour en local immédiatement, pas de second appel
    this.notifications.update(list =>
      list.map(n => n.id === id ? { ...n, is_read: true } : n)
    );
    this.unreadCount.update(c => Math.max(0, c - 1));

    // Appel backend en arrière-plan, silent
    return this.http.patch(`${this.apiUrl}/notifications/${id}/read`, {});
  }

  markAllAsRead() {
    // ⚡ Même logique
    this.notifications.update(list => list.map(n => ({ ...n, is_read: true })));
    this.unreadCount.set(0);

    return this.http.post(`${this.apiUrl}/notifications/mark-all-read`, {});
  }

  addNotification(notification: Notification) {
    this.notifications.update(list => [notification, ...list]);
    this.unreadCount.update(count => count + 1);
  }
}