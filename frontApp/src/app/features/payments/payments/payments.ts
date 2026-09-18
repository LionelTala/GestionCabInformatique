import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { PaymentService } from '../../../core/services/payment.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

@Component({
  selector: 'app-payments',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule],
  templateUrl: './payments.html',
  styleUrl: './payments.css'
})
export class PaymentsComponent implements OnInit {
  private paymentService = inject(PaymentService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  payments = signal<any[]>([]);
  meta = signal<any>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
  periodMeta = signal<any>({ total_amount: 0, total_count: 0, date_from: '', date_to: '' });
  loading = signal(false);
  submitting = signal(false);

  // ✅ Loader PAR LIGNE : Set d'IDs en cours de téléchargement
  downloadingIds = signal<Set<number>>(new Set<number>());

  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  // === FILTRES ===
  filters = signal({
    campus_id: null as number | null,
    search: '',
    period: 'today' as 'today' | 'week' | 'month' | 'year' | 'custom' | 'all',
    date_from: '' as string,
    date_to: '' as string,
  });

  // === MODAL NOUVEAU PAIEMENT ===
  showModal = signal(false);
  modalStep = signal<1 | 2>(1);
  searchQuery = signal('');
  searchResults = signal<any[]>([]);
  searching = signal(false);

  selectedStudent = signal<any>(null);
  paymentAmount = signal<number | null>(null);

  // === MODAL SUPPRESSION ===
  showDeleteModal = signal(false);
  paymentToDelete = signal<any>(null);

  // === CALCUL EN TEMPS RÉEL ===
  newBalance = computed(() => {
    const student = this.selectedStudent();
    const amount = this.paymentAmount();
    if (!student || amount === null || amount === undefined) return student?.balance || 0;
    return Math.max(0, student.balance - amount);
  });

  ngOnInit() {
    this.campusService.loadCampuses();
    this.loadPayments(1);
  }

  // ═══ HELPERS LOADER ═══
  isDownloading(id: number): boolean {
    return this.downloadingIds().has(id);
  }

  private addDownloading(id: number) {
    this.downloadingIds.update(s => new Set(s).add(id));
  }

  private removeDownloading(id: number) {
    this.downloadingIds.update(s => {
      const next = new Set(s);
      next.delete(id);
      return next;
    });
  }

  // ═══ CHARGEMENT & FILTRES ═══
  loadPayments(page = 1) {
    this.loading.set(true);
    const f = this.filters();
    const params: any = { page, period: f.period };
    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.search) params.search = f.search;
    if (f.period === 'custom') {
      if (f.date_from) params.date_from = f.date_from;
      if (f.date_to) params.date_to = f.date_to;
    }

    this.paymentService.getRecentPayments(page, params).subscribe({
      next: (res: any) => {
        this.payments.set(res.data.data);
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
      error: () => this.loading.set(false)
    });
  }

  applyFilters() { this.loadPayments(1); }

  resetFilters() {
    this.filters.set({
      campus_id: null, search: '', period: 'today', date_from: '', date_to: ''
    });
    this.loadPayments(1);
  }

  setPeriod(period: 'today' | 'week' | 'month' | 'year' | 'custom' | 'all') {
    this.filters.update(f => ({ ...f, period }));
    this.loadPayments(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) this.loadPayments(page);
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

  // ═══ MODAL NOUVEAU PAIEMENT ═══
  openModal() {
    this.showModal.set(true);
    this.modalStep.set(1);
    this.searchQuery.set('');
    this.searchResults.set([]);
    this.selectedStudent.set(null);
    this.paymentAmount.set(null);
  }

  closeModal() { this.showModal.set(false); }

  onSearch() {
    const q = this.searchQuery().trim();
    if (q.length < 2) { this.searchResults.set([]); return; }

    this.searching.set(true);
    this.paymentService.searchStudents(q).subscribe({
      next: (res) => {
        this.searchResults.set(res.data);
        this.searching.set(false);
      },
      error: () => this.searching.set(false)
    });
  }

  selectStudent(student: any) {
    this.selectedStudent.set(student);
    this.modalStep.set(2);
    this.paymentAmount.set(null);
  }

  goBackToSearch() {
    this.modalStep.set(1);
    this.selectedStudent.set(null);
    this.paymentAmount.set(null);
  }

  validatePayment() {
    const amount = this.paymentAmount();
    const student = this.selectedStudent();
    if (!student || !amount || amount <= 0) {
      this.toastr.warning('Veuillez entrer un montant valide');
      return;
    }

    this.submitting.set(true);
    this.paymentService.createPayment(student.id, {
      amount: amount,
      payment_date: new Date().toISOString().split('T')[0],
      reference: 'PAY-' + Date.now()
    }).subscribe({
      next: (res: any) => {
        this.toastr.success('Paiement enregistré avec succès');
        this.submitting.set(false);
        this.closeModal();
        this.loadPayments(this.meta().current_page);
        // ✅ Ouvre automatiquement le reçu
        this.downloadReceipt(res.data.id);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du paiement');
        this.submitting.set(false);
      }
    });
  }

  // ═══ SUPPRESSION ═══
  openDeleteModal(payment: any) {
    this.paymentToDelete.set(payment);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal() {
    this.showDeleteModal.set(false);
    this.paymentToDelete.set(null);
  }

  confirmDelete() {
    const payment = this.paymentToDelete();
    if (!payment) return;

    this.submitting.set(true);
    this.paymentService.deletePayment(payment.id).subscribe({
      next: () => {
        this.toastr.success('Paiement annulé avec succès');
        this.closeDeleteModal();
        this.loadPayments(this.meta().current_page);
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de l\'annulation');
        this.submitting.set(false);
      }
    });
  }

  canDelete(): boolean {
    const user = this.currentUser;
    return user && ['super_admin', 'admin_global', 'admin_campus'].includes(user.role);
  }

  // ═══════════════════════════════════════════════════════════
  // ✅ TÉLÉCHARGEMENT DU REÇU (avec loader par ligne)
  // ═══════════════════════════════════════════════════════════
  downloadReceipt(paymentId: number) {
    // ✅ Active le loader pour cette ligne
    this.addDownloading(paymentId);

    // ═══ ÉTAPE 1 : Récupérer l'URL signée ═══
    this.paymentService.getReceiptDownloadUrl(paymentId).subscribe({
      next: (res) => {
        // ═══ ÉTAPE 2 : Télécharger le PDF via HttpClient ═══
        this.paymentService.downloadFromUrl(res.url).subscribe({
          next: (blob) => {
            // ═══ ÉTAPE 3 : Déclencher le téléchargement natif ═══
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `recu-${paymentId}.pdf`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();

            // Nettoyage
            setTimeout(() => {
              document.body.removeChild(link);
              window.URL.revokeObjectURL(url);
              this.removeDownloading(paymentId);
            }, 100);
          },
          error: () => {
            this.toastr.error('Erreur lors du téléchargement du reçu');
            this.removeDownloading(paymentId);
          },
        });
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la préparation du reçu');
        this.removeDownloading(paymentId);
      },
    });
  }

  // ═══ UTILITAIRES ═══
  formatPrice(price: number) { return (price || 0).toLocaleString('fr-FR') + ' FCFA'; }

  canSeeAllCampuses() {
    return this.currentUser && ['super_admin', 'admin_global'].includes(this.currentUser.role);
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