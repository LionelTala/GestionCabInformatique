import { Component, signal, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ExpenseService } from '../../../core/services/expense.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

@Component({
  selector: 'app-expenses',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './expenses.html',
  styleUrl: './expenses.css'
})
export class ExpensesComponent implements OnInit {
  private expenseService = inject(ExpenseService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  expenses = this.expenseService.getExpenses();
  meta = this.expenseService.getMeta();
  loading = this.expenseService.getLoading();
  totalAmount = this.expenseService.getTotalAmount();
  summary = this.expenseService.getSummary();
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  currentPage = signal(1);
  submitting = signal(false);

  // Filtres
  filters = signal({
    campus_id: null as number | null,
    category: '' as string,
    year: null as number | null,
    period: '' as string,
    search: '',
  });

  // Liste des années disponibles (année courante - 5 ans)
  years = Array.from({ length: 6 }, (_, i) => new Date().getFullYear() - i);

  // Modal Création
  showModal = signal(false);
  formData = signal({
    campus_id: null as number | null,
    category: '' as string,
    amount: null as number | null,
    description: '',
  });
  errors = signal({
    campus_id: '',
    category: '',
    amount: '',
    description: '',
  });

  // Modal Suppression
  showDeleteModal = signal(false);
  expenseToDelete = signal<any>(null);

  ngOnInit() {
    this.campusService.loadCampuses();

    // Pré-remplir le campus pour les rôles limités
    const user = this.currentUser;
    if (user && ['admin_campus', 'secretary'].includes(user.role)) {
      this.formData.update(d => ({ ...d, campus_id: user.campus_id }));
    }

    this.loadExpenses(1);
    this.loadSummary();
  }

    loadExpenses(page: number = 1) {
    this.currentPage.set(page);
    const f = this.filters();
    
    // On part d'un objet propre avec la pagination
    const params: any = { page, per_page: 15 };

    // ✅ CORRECTION TYPE-SAFE : != null vérifie à la fois null et undefined
    if (f.campus_id != null) {
      params.campus_id = f.campus_id;
    }

    if (f.category) {
      params.category = f.category;
    }

    if (f.year != null) {
      params.year = f.year;
    }

    if (f.period) {
      params.period = f.period;
    }

    if (f.search && f.search.trim() !== '') {
      params.search = f.search.trim();
    }

    this.expenseService.loadExpenses(page, params);
  }

  loadSummary() {
    const f = this.filters();
    const params: any = {};

    // ✅ CORRECTION TYPE-SAFE
    if (f.campus_id != null) {
      params.campus_id = f.campus_id;
    }

    this.expenseService.loadSummary(params);
  }

 

  applyFilters() {
    this.loadExpenses(1);
    this.loadSummary();
  }

  resetFilters() {
    this.filters.set({ campus_id: null, category: '', year: null, period: '', search: '' });
    this.loadExpenses(1);
    this.loadSummary();
  }
    getCampusName(campusId: number | null): string {
    if (!campusId) return '-';
    const campus = this.campuses().find(c => c.id === campusId);
    return campus ? campus.name : '-';
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) {
      this.loadExpenses(page);
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

  // === MODAL CRÉATION ===
  openCreateModal() {
    const user = this.currentUser;
    this.formData.set({
      campus_id: user && ['admin_campus', 'secretary'].includes(user.role) ? user.campus_id : null,
      category: '',
      amount: null,
      description: '',
    });
    this.errors.set({ campus_id: '', category: '', amount: '', description: '' });
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.errors.set({ campus_id: '', category: '', amount: '', description: '' });
  }

  validateForm(): boolean {
    const data = this.formData();
    let valid = true;
    const errs: any = { campus_id: '', category: '', amount: '', description: '' };

    if (!data.campus_id) { errs.campus_id = 'Le campus est obligatoire'; valid = false; }
    if (!data.category) { errs.category = 'La catégorie est obligatoire'; valid = false; }
    if (!data.amount || data.amount <= 0) { errs.amount = 'Le montant doit être supérieur à 0'; valid = false; }
    if (!data.description?.trim()) { errs.description = 'La description est obligatoire'; valid = false; }

    this.errors.set(errs);
    return valid;
  }

  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs');
      return;
    }

    this.submitting.set(true);
    this.expenseService.createExpense(this.formData()).subscribe({
      next: () => {
        this.toastr.success('Dépense enregistrée avec succès');
        this.closeModal();
        this.loadExpenses(this.currentPage());
        this.loadSummary();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de l\'enregistrement');
        this.submitting.set(false);
      }
    });
  }

  // === MODAL SUPPRESSION ===
  openDeleteModal(expense: any) {
    this.expenseToDelete.set(expense);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.expenseToDelete.set(null);
  }

  confirmDelete() {
    const expense = this.expenseToDelete();
    if (!expense) return;

    this.submitting.set(true);
    this.expenseService.deleteExpense(expense.id).subscribe({
      next: () => {
        this.toastr.success('Dépense supprimée avec succès');
        this.closeDeleteModal();
        this.loadExpenses(this.currentPage());
        this.loadSummary();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      }
    });
  }

  // === UTILITAIRES ===
  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  formatDateTime(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  getCategoryLabel(category: string): string {
    const labels: Record<string, string> = {
      salary: 'Salaire',
      other: 'Autre dépense',
    };
    return labels[category] || category;
  }

  getCategoryIcon(category: string): string {
    const icons: Record<string, string> = {
      salary: 'payments',
      other: 'receipt_long',
    };
    return icons[category] || 'receipt';
  }

  getCategoryClass(category: string): string {
    const classes: Record<string, string> = {
      salary: 'bg-purple-100 text-purple-700',
      other: 'bg-orange-100 text-orange-700',
    };
    return classes[category] || 'bg-gray-100 text-gray-700';
  }

  getPeriodLabel(period: string): string {
    const labels: Record<string, string> = {
      today: "Aujourd'hui",
      this_week: 'Cette semaine',
      this_month: 'Ce mois-ci',
      last_month: 'Le mois dernier',
      this_year: 'Cette année',
    };
    return labels[period] || 'Toutes les périodes';
  }

  canSeeAllCampuses(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  canDelete(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global'].includes(user.role);
  }

  canCreate(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global', 'admin_campus', 'secretary'].includes(user.role);
  }
}