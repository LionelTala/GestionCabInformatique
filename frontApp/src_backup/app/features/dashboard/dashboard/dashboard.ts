// src/app/features/dashboard/dashboard/dashboard.ts
import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { DashboardService, DashboardPeriod } from '../../../core/services/dashboard.service';
import { Auth } from '../../../core/services/auth';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.css'
})
export class DashboardComponent implements OnInit {
  private dashboardService = inject(DashboardService);
  private auth = inject(Auth);

  stats = this.dashboardService.getStats();
  loading = this.dashboardService.getLoading();
  currentUser = this.auth.getUser();

  // ✅ Filtres de période
  period = signal<DashboardPeriod>('today');
  dateFrom = signal<string>('');
  dateTo = signal<string>('');

  // ✅ Liste des périodes pour le template
  periods = [
    { key: 'today' as const, label: "Aujourd'hui", short: "Auj.", icon: 'today' },
    { key: 'week' as const, label: 'Cette semaine', short: 'Sem.', icon: 'date_range' },
    { key: 'month' as const, label: 'Ce mois', short: 'Mois', icon: 'calendar_month' },
    { key: 'year' as const, label: 'Cette année', short: 'Année', icon: 'calendar_today' },
    { key: 'custom' as const, label: 'Personnalisée', short: 'Perso', icon: 'edit_calendar' },
  ];

  ngOnInit() {
    this.loadStats();
  }

  loadStats() {
    this.dashboardService.loadStats({
      period: this.period(),
      date_from: this.dateFrom() || undefined,
      date_to: this.dateTo() || undefined,
    });
  }

  setPeriod(p: DashboardPeriod) {
    this.period.set(p);
    this.loadStats();
  }

  // ✅ Helpers
  isSuperAdmin(): boolean {
    const user = this.currentUser;
    return !!user && ['super_admin', 'admin_global'].includes(user.role);
  }

  isSecretary(): boolean {
    return this.currentUser?.role === 'secretary';
  }

  getRoleTitle(): string {
    const user = this.currentUser;
    if (!user) return 'Utilisateur';
    if (user.role === 'secretary') return 'Secrétariat';
    if (user.role === 'admin_campus') return `Admin - ${user.campus?.name || 'Campus'}`;
    return 'Administration Globale';
  }

  getPeriodLabel(): string {
    const labels: Record<DashboardPeriod, string> = {
      today: "Aujourd'hui",
      week: 'Cette semaine',
      month: 'Ce mois',
      year: 'Cette année',
      custom: 'Personnalisée',
      all: 'Tout',
    };
    return labels[this.period()] || this.period();
  }

  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatPriceShort(price: number): string {
    if (!price) return '0 FCFA';
    const abs = Math.abs(price);
    const sign = price < 0 ? '-' : '';
    if (abs >= 1000000) return sign + (abs / 1000000).toFixed(1) + 'M';
    if (abs >= 1000) return sign + (abs / 1000).toFixed(0) + 'K';
    return sign + abs.toLocaleString('fr-FR');
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  getInitials(name: string): string {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
  }

  getCategoryLabel(category: string): string {
    const labels: Record<string, string> = {
      donation: 'Don',
      subvention: 'Subvention',
      other_income: 'Autre recette',
      salary: 'Salaire',
      supplies: 'Fournitures',
      rent: 'Loyer',
      utilities: 'Eau/Élec/Internet',
      maintenance: 'Entretien',
      marketing: 'Publicité',
      transport: 'Transport',
      other_expense: 'Autre dépense',
      // Contre-écritures
      tuition_refund: 'Remboursement',
      registration_cancel: 'Annulation inscription',
      payment_cancel: 'Annulation paiement',
    };
    return labels[category] || category;
  }
}