import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { FinancialMovementService } from '../../../core/services/financial-movement.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

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
  summary = this.movementService.getSummary();
  loading = this.movementService.getLoading();
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  filters = signal({
    campus_id: null as number | null,
    type: '' as string,
    month: null as number | null,
    year: null as number | null,
  });

  years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);
  months = [
    { value: 1, label: 'Janvier' }, { value: 2, label: 'Février' },
    { value: 3, label: 'Mars' }, { value: 4, label: 'Avril' },
    { value: 5, label: 'Mai' }, { value: 6, label: 'Juin' },
    { value: 7, label: 'Juillet' }, { value: 8, label: 'Août' },
    { value: 9, label: 'Septembre' }, { value: 10, label: 'Octobre' },
    { value: 11, label: 'Novembre' }, { value: 12, label: 'Décembre' }
  ];

  ngOnInit() {
    this.campusService.loadCampuses();
    this.loadMovements();
  }

  loadMovements() {
    const f = this.filters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.type) params.type = f.type;
    if (f.month != null) params.month = f.month;
    if (f.year != null) params.year = f.year;

    this.movementService.loadMovements(params);
  }

  applyFilters() { this.loadMovements(); }

  resetFilters() {
    this.filters.set({ campus_id: null, type: '', month: null, year: null });
    this.loadMovements();
  }

  generateReport() {
    const f = this.filters();
    const params: any = {};
    if (f.campus_id != null) params.campus_id = f.campus_id;
    if (f.type) params.type = f.type;
    if (f.month != null) params.month = f.month;
    if (f.year != null) params.year = f.year;

    this.movementService.generateReport(params);
  }

  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('fr-FR');
  }

  canSeeAllCampuses(): boolean {
    return this.currentUser && ['super_admin', 'admin_global'].includes(this.currentUser.role);
  }
}