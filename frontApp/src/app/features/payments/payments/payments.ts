import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { PaymentService } from '../../../core/services/payment.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

@Component({
  selector: 'app-payments',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './payments.html',
  styleUrl: './payments.css'
})
export class PaymentsComponent implements OnInit {
  private paymentService = inject(PaymentService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  payments = signal<any[]>([]);
  meta = signal<any>({ current_page: 1, last_page: 1, total: 0 });
  loading = signal(false);
  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  // Filtres
  filters = signal({ campus_id: null as number | null, search: '' });

  // Modal
  showModal = signal(false);
  modalStep = signal<1 | 2>(1); // 1: Recherche, 2: Paiement
  searchQuery = signal('');
  searchResults = signal<any[]>([]);
  searching = signal(false);

  selectedStudent = signal<any>(null);
  paymentAmount = signal<number | null>(null);
  submitting = signal(false);
  downloading = signal(false);

  // Calcul en temps réel du nouveau reste à payer
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

  loadPayments(page = 1) {
    this.loading.set(true);
    const f = this.filters();
    const params: any = { page };
    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.search) params.search = f.search;

    this.paymentService.getRecentPayments(page, params).subscribe({
      next: (res) => {
        this.payments.set(res.data.data);
        this.meta.set({ current_page: res.data.current_page, last_page: res.data.last_page, total: res.data.total });
        this.loading.set(false);
      },
      error: () => this.loading.set(false)
    });
  }

  applyFilters() { this.loadPayments(1); }

  // --- LOGIQUE MODAL ---
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
      reference: 'PAY-' + Date.now() // Ou laisser le backend générer
    }).subscribe({
      next: (res: any) => {
        this.toastr.success('Paiement enregistré avec succès');
        this.submitting.set(false);
        this.closeModal();
        this.loadPayments(this.meta().current_page);
        this.downloadReceipt(res.data.id); // Téléchargement automatique
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du paiement');
        this.submitting.set(false);
      }
    });
  }

  downloadReceipt(paymentId: number) {
    this.downloading.set(true);
    this.paymentService.downloadReceipt(paymentId).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `recu-paiement.pdf`;
        link.click();
        window.URL.revokeObjectURL(url);
        this.downloading.set(false);
      },
      error: () => this.downloading.set(false)
    });
  }

  formatPrice(price: number) { return (price || 0).toLocaleString('fr-FR') + ' FCFA'; }
  canSeeAllCampuses() { return this.currentUser && ['super_admin', 'admin_global'].includes(this.currentUser.role); }
}