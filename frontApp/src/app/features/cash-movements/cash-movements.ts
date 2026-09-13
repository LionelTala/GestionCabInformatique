// src/app/features/cash-movements/cash-movements/cash-movements.ts
import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { CashMovementService, CashSummary } from '../../core/services/cash-movement.service';
import { Auth } from '../../core/services/auth';
import { CampusService } from '../../core/services/campus';

type Period = 'today' | 'week' | 'month' | 'year' | 'custom' | 'all';
type MovementType = 'income' | 'expense';

@Component({
  selector: 'app-cash-movements',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './cash-movements.html',
  styleUrl: './cash-movements.css'
})
export class CashMovementsComponent implements OnInit {
  private cashService = inject(CashMovementService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  // ═══ DONNÉES ═══
  movements = signal<any[]>([]);
  meta = signal<any>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
  periodMeta = signal<any>({
    period: 'today',
    date_from: '',
    date_to: '',
    total_income: 0,
    total_expense: 0,
    balance: 0,
    total_count: 0,
  });
  summary = signal<CashSummary>({
    today: { income: 0, expense: 0, balance: 0 },
    month: { income: 0, expense: 0, balance: 0 },
    year:  { income: 0, expense: 0, balance: 0 },
    total: { income: 0, expense: 0, balance: 0 },
  });

  loading = signal(false);
  submitting = signal(false);
  downloading = signal(false);

  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  // ═══ CATÉGORIES ═══
  incomeCategories = signal<Record<string, string>>({});
  expenseCategories = signal<Record<string, string>>({});

  // ═══ FILTRES ═══
  filters = signal({
    campus_id: null as number | null,
    type: '' as '' | MovementType,
    category: '',
    search: '',
    period: 'today' as Period,
    date_from: '',
    date_to: '',
  });

  // ═══ MODAL CRÉATION ═══
  showModal = signal(false);
  modalType = signal<MovementType>('income');
  formData = signal({
    campus_id: null as number | null,
    category: '',
    amount: null as number | null,
    title: '',
    description: '',
  });
  selectedFile = signal<File | null>(null);
  filePreview = signal<string | null>(null);
  fileError = signal<string>('');

  errors = signal({
    campus_id: '',
    category: '',
    amount: '',
    title: '',
  });

  // ═══ MODAL SUPPRESSION ═══
  showDeleteModal = signal(false);
  movementToDelete = signal<any>(null);

  // ═══ COMPUTED ═══
  modalTitle = computed(() =>
    this.modalType() === 'income' ? 'Nouvelle entrée en caisse' : 'Nouvelle sortie de caisse'
  );

  availableCategories = computed(() =>
    this.modalType() === 'income' ? this.incomeCategories() : this.expenseCategories()
  );

  ngOnInit() {
    this.campusService.loadCampuses();
    this.loadCategories();
    this.loadSummary();
    this.loadMovements(1);
  }

  // ═══ CATÉGORIES ═══
  loadCategories() {
    this.cashService.getCategories().subscribe({
      next: (res) => {
        this.incomeCategories.set(res.data.income || {});
        this.expenseCategories.set(res.data.expense || {});
      },
      error: () => this.toastr.error('Erreur chargement des catégories'),
    });
  }

  // ═══ RÉSUMÉ KPIs ═══
  loadSummary() {
    const f = this.filters();
    const params: any = {};
    if (f.campus_id) params.campus_id = f.campus_id;

    this.cashService.getSummary(params).subscribe({
      next: (res) => this.summary.set(res.data),
      error: () => {},
    });
  }

  // ═══ LISTE ═══
  loadMovements(page = 1) {
    this.loading.set(true);
    const f = this.filters();
    const params: any = { page, period: f.period };

    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.type)      params.type = f.type;
    if (f.category)  params.category = f.category;
    if (f.search)    params.search = f.search;
    if (f.period === 'custom') {
      if (f.date_from) params.date_from = f.date_from;
      if (f.date_to)   params.date_to = f.date_to;
    }

    this.cashService.getMovements(page, params).subscribe({
      next: (res: any) => {
        this.movements.set(res.data.data);
        this.meta.set({
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          total: res.data.total,
          from: res.data.from,
          to: res.data.to,
        });
        if (res.meta) this.periodMeta.set(res.meta);
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  applyFilters() { this.loadMovements(1); }

  resetFilters() {
    this.filters.set({
      campus_id: null, type: '', category: '', search: '',
      period: 'today', date_from: '', date_to: '',
    });
    this.loadMovements(1);
  }

  setPeriod(period: Period) {
    this.filters.update(f => ({ ...f, period }));
    this.loadMovements(1);
  }

  setType(type: '' | MovementType) {
    this.filters.update(f => ({ ...f, type, category: '' }));
    this.loadMovements(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) this.loadMovements(page);
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

  // ═══ MODAL CRÉATION ═══
  openModal(type: MovementType) {
    const user = this.currentUser;
    const isRestricted = user && ['admin_campus', 'secretary'].includes(user.role);

    this.modalType.set(type);
    this.formData.set({
      campus_id: isRestricted ? user.campus_id : null,
      category: '',
      amount: null,
      title: '',
      description: '',
    });
    this.errors.set({ campus_id: '', category: '', amount: '', title: '' });
    this.selectedFile.set(null);
    this.filePreview.set(null);
    this.fileError.set('');
    this.showModal.set(true);
  }

  closeModal() {
    this.showModal.set(false);
    this.selectedFile.set(null);
    this.filePreview.set(null);
    this.fileError.set('');
  }

  // ═══ FICHIER ═══
  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const maxSize = 5 * 1024 * 1024;
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];

    if (file.size > maxSize) {
      this.fileError.set('Fichier trop volumineux (max 5 Mo)');
      this.selectedFile.set(null);
      this.filePreview.set(null);
      return;
    }

    if (!allowedTypes.includes(file.type)) {
      this.fileError.set('Format non supporté (PDF, JPG, PNG uniquement)');
      this.selectedFile.set(null);
      this.filePreview.set(null);
      return;
    }

    this.fileError.set('');
    this.selectedFile.set(file);

    if (file.type.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = () => this.filePreview.set(reader.result as string);
      reader.readAsDataURL(file);
    } else {
      this.filePreview.set(null);
    }
  }

  removeFile() {
    this.selectedFile.set(null);
    this.filePreview.set(null);
    this.fileError.set('');
    const input = document.getElementById('cashFileInput') as HTMLInputElement;
    if (input) input.value = '';
  }

  openFileSelector() {
    const input = document.getElementById('cashFileInput') as HTMLInputElement;
    if (input) input.click();
  }

  // ═══ VALIDATION ═══
  validateForm(): boolean {
    const data = this.formData();
    let valid = true;
    const newErrors = { campus_id: '', category: '', amount: '', title: '' };

    if (!data.campus_id) {
      newErrors.campus_id = 'Le campus est obligatoire';
      valid = false;
    }
    if (!data.category) {
      newErrors.category = 'La catégorie est obligatoire';
      valid = false;
    }
    if (!data.amount || data.amount <= 0) {
      newErrors.amount = 'Le montant doit être supérieur à 0';
      valid = false;
    }
    if (!data.title?.trim()) {
      newErrors.title = 'Le titre est obligatoire';
      valid = false;
    }

    this.errors.set(newErrors);
    return valid;
  }

  // ═══ SOUMISSION ═══
  onSubmit() {
    if (!this.validateForm()) {
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
      return;
    }

    const data = this.formData();
    const formData = new FormData();

    formData.append('campus_id', data.campus_id!.toString());
    formData.append('type', this.modalType());
    formData.append('category', data.category);
    formData.append('amount', data.amount!.toString());
    formData.append('title', data.title);
    if (data.description?.trim()) {
      formData.append('description', data.description);
    }
    if (this.selectedFile()) {
      formData.append('attachment', this.selectedFile()!);
    }

    this.submitting.set(true);
    this.cashService.createMovement(formData).subscribe({
      next: () => {
        this.toastr.success('Mouvement enregistré avec succès');
        this.closeModal();
        this.loadMovements(this.meta().current_page);
        this.loadSummary();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || "Erreur lors de l'enregistrement");
        this.submitting.set(false);
      },
    });
  }

  // ═══ SUPPRESSION ═══
  openDeleteModal(movement: any) {
    this.movementToDelete.set(movement);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.movementToDelete.set(null);
  }

  confirmDelete() {
    const movement = this.movementToDelete();
    if (!movement) return;

    this.submitting.set(true);
    this.cashService.deleteMovement(movement.id).subscribe({
      next: () => {
        this.toastr.success('Mouvement supprimé avec succès');
        this.closeDeleteModal();
        this.loadMovements(this.meta().current_page);
        this.loadSummary();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        this.submitting.set(false);
      },
    });
  }

  // ═══ PIÈCE JOINTE ═══
  downloadAttachment(movement: any) {
    if (!movement.attachment_path) {
      this.toastr.warning('Aucune pièce jointe pour ce mouvement');
      return;
    }

    this.downloading.set(true);
    this.cashService.downloadAttachment(movement.id).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');
        setTimeout(() => {
          window.URL.revokeObjectURL(url);
          this.downloading.set(false);
        }, 1000);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du téléchargement');
        this.downloading.set(false);
      },
    });
  }

  // ═══ UTILITAIRES ═══
  formatPrice(price: number): string {
    return (price || 0).toLocaleString('fr-FR') + ' FCFA';
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  getCategoryLabel(category: string, type: MovementType): string {
    const dict = type === 'income' ? this.incomeCategories() : this.expenseCategories();
    return dict[category] || category;
  }

  canSeeAllCampuses(): boolean {
    const user = this.currentUser;
    return !!user && ['super_admin', 'admin_global'].includes(user.role);
  }

  getPeriodLabel(): string {
    const p = this.filters().period;
    const labels: Record<string, string> = {
      today: "Aujourd'hui",
      week: 'Cette semaine',
      month: 'Ce mois',
      year: 'Cette année',
      custom: 'Personnalisée',
      all: 'Tout',
    };
    return labels[p] || p;
  }
}