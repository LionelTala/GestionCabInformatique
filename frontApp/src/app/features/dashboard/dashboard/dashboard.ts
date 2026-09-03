import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DashboardService } from '../../../core/services/dashboard.service'; 
import { Auth } from '../../../core/services/auth';
@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class DashboardComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private auth = inject(Auth);

  stats = this.dashboardService.getStats();
  loading = this.dashboardService.getLoading();
  currentUser = this.auth.getUser();

  ngOnInit() {
    this.dashboardService.loadStats();
  }

 

 
 
 
    isSuperAdmin(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  getRoleTitle(): string {
    const user = this.currentUser;
    if (!user) return 'Utilisateur';
    if (user.role === 'secretary') return 'Secrétariat';
    if (user.role === 'admin_campus') return `Admin - ${user.campus?.name || 'Campus'}`;
    return 'Administration Globale';
  }

  getCurrentMonth(): string {
    return new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
  }

  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  // ✅ Version courte pour les cartes (ex: "1.5M FCFA")
  formatPriceShort(price: number): string {
    if (!price) return '0 FCFA';
    if (price >= 1000000) return (price / 1000000).toFixed(1) + 'M FCFA';
    if (price >= 1000) return (price / 1000).toFixed(0) + 'K FCFA';
    return price.toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
  }

  getInitials(name: string): string {
    if (!name) return '?';
    return name
      .split(' ')
      .map(n => n[0])
      .join('')
      .toUpperCase()
      .substring(0, 2); // Limite à 2 lettres
  }
}