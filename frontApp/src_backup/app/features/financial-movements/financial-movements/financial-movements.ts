// src/app/features/financial-movements/financial-movements/financial-movements.ts
import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { FinancialMovementService } from '../../../core/services/financial-movement.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

type Period = 'today' | 'week' | 'month' | 'year' | 'custom' | 'all';

@Component({
  selector: 'app-financial-movements',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './financial-movements.html',
  styleUrl: './financial-movements.css'
})
export class FinancialMovementsComponent implements OnInit {
  private movementService = inject(FinancialMovementService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);

  movements = this.movementService.getMovements();
  meta = this.movementService.getMeta();
  summary = this.movementService.getSummary();
  periodMeta = this.movementService.getPeriodMeta();
  loading = this.movementService.getLoading();
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  currentPage = signal(1);

  // ═══ FILTRES ═══
  filters = signal({
    campus_id: null as number | null,
    type: '' as string,
    category: '',
    search: '',
    period: 'today' as Period,
    date_from: '',
    date_to: '',
  });

  // ═══ OPTIONS ═══
  periodOptions = [
    { key: 'today' as const, label: "Aujourd'hui", short: 'Auj.', icon: 'today' },
    { key: 'week' as const, label: 'Cette semaine', short: 'Sem.', icon: 'date_range' },
    { key: 'month' as const, label: 'Ce mois', short: 'Mois', icon: 'calendar_month' },
    { key: 'year' as const, label: 'Cette année', short: 'Année', icon: 'calendar_today' },
    { key: 'custom' as const, label: 'Personnalisé', short: 'Perso', icon: 'edit_calendar' },
    { key: 'all' as const, label: 'Tout', short: 'Tout', icon: 'all_inclusive' },
  ];

  ngOnInit() {
    this.campusService.loadCampuses();
    this.loadMovements(1);
  }

  // ═══ CHARGEMENT ═══
  loadMovements(page = 1) {
    this.currentPage.set(page);
    const f = this.filters();
    const params: any = { period: f.period };

    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.type)      params.type = f.type;
    if (f.category)  params.category = f.category;
    if (f.search)    params.search = f.search;
    if (f.period === 'custom') {
      if (f.date_from) params.date_from = f.date_from;
      if (f.date_to)   params.date_to = f.date_to;
    }

    this.movementService.loadMovements(page, params);
  }

  applyFilters() {
    this.currentPage.set(1);
    this.loadMovements(1);
  }

  resetFilters() {
    this.filters.set({
      campus_id: null,
      type: '',
      category: '',
      search: '',
      period: 'today',
      date_from: '',
      date_to: '',
    });
    this.loadMovements(1);
  }

  setPeriod(period: Period) {
    this.filters.update(f => ({ ...f, period }));
    this.currentPage.set(1);
    this.loadMovements(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) {
      this.loadMovements(page);
    }
  }

  getPages(): number[] {
    const total = this.meta().last_page;
    const current = this.meta().current_page;
    const pages: number[] = [];
    if (total <= 7) {
      for (let i = 1; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      if (current > 3) pages.push(-1);
      for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) pages.push(i);
      if (current < total - 2) pages.push(-1);
      pages.push(total);
    }
    return pages;
  }

  // ═══ RAPPORT PDF ═══
  generateReport() {
    const f = this.filters();
    const params: any = { period: f.period };

    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.type)      params.type = f.type;
    if (f.category)  params.category = f.category;
    if (f.period === 'custom') {
      if (f.date_from) params.date_from = f.date_from;
      if (f.date_to)   params.date_to = f.date_to;
    }

    this.movementService.generateReport(params);
  }

  // ═══ UTILITAIRES ═══
  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatPriceShort(price: number): string {
    if (!price) return '0';
    const abs = Math.abs(price);
    const sign = price < 0 ? '-' : '';
    if (abs >= 1000000) return sign + (abs / 1000000).toFixed(1) + 'M';
    if (abs >= 1000) return sign + (abs / 1000).toFixed(0) + 'K';
    return sign + abs.toLocaleString('fr-FR');
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit',
    });
  }

  canSeeAllCampuses(): boolean {
    const user = this.currentUser;
    return !!user && ['super_admin', 'admin_global'].includes(user.role);
  }

  getPeriodLabel(): string {
    const labels: Record<Period, string> = {
      today: "Aujourd'hui",
      week: 'Cette semaine',
      month: 'Ce mois',
      year: 'Cette année',
      custom: 'Personnalisée',
      all: 'Toutes les périodes',
    };
    return labels[this.filters().period] || "Aujourd'hui";
  }
}