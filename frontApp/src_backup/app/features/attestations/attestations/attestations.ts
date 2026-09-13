// src/app/features/attestations/attestations/attestations.ts
import { Component, signal, OnInit, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AttestationService, Attestation } from '../../../core/services/attestation.service';
import { CampusService } from '../../../core/services/campus';
import { Auth } from '../../../core/services/auth';

type Tab = 'pending' | 'ready';

@Component({
  selector: 'app-attestations',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './attestations.html',
  styleUrl: './attestations.css'
})
export class AttestationsComponent implements OnInit {
  private attestationService = inject(AttestationService);
  private campusService = inject(CampusService);
  private auth = inject(Auth);
  private toastr = inject(ToastrService);

  // ═══ DONNÉES ═══
  attestations = signal<Attestation[]>([]);
  meta = signal<any>({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 });
  stats = signal({ pending: 0, ready: 0, total: 0 });
  loading = signal(false);
  submitting = signal(false);

  campuses = this.campusService.getCampuses();
  currentUser = this.auth.getUser();

  // ═══ ONGLET ACTIF ═══
  activeTab = signal<Tab>('pending');

  // ═══ FILTRES ═══
  filters = signal({
    campus_id: null as number | null,
    search: '',
  });

  // ═══ MODAL NOUVELLE DEMANDE ═══
  showModal = signal(false);
  modalStep = signal<1 | 2>(1);
  searchQuery = signal('');
  searchResults = signal<any[]>([]);
  searching = signal(false);
  selectedStudent = signal<any>(null);

  // ═══ MODAL RÉGLER ═══
  showSettleModal = signal(false);
  attestationToSettle = signal<Attestation | null>(null);

  // ═══ MODAL REMETTRE EN ATTENTE ═══
  showUnsettleModal = signal(false);
  attestationToUnsettle = signal<Attestation | null>(null);

  ngOnInit() {
    this.campusService.loadCampuses();
    this.loadStats();
    this.loadAttestations(1);
  }

  // ═══ CHARGEMENT ═══
  loadAttestations(page = 1) {
    this.loading.set(true);
    const f = this.filters();
    const params: any = { page, status: this.activeTab() };

    if (f.campus_id) params.campus_id = f.campus_id;
    if (f.search)    params.search = f.search;

    this.attestationService.getAttestations(page, params).subscribe({
      next: (res: any) => {
        this.attestations.set(res.data.data);
        this.meta.set({
          current_page: res.data.current_page,
          last_page: res.data.last_page,
          total: res.data.total,
          from: res.data.from,
          to: res.data.to,
        });
        if (res.meta) {
          this.stats.set({
            pending: res.meta.pending_count ?? 0,
            ready: res.meta.ready_count ?? 0,
            total: (res.meta.pending_count ?? 0) + (res.meta.ready_count ?? 0),
          });
        }
        this.loading.set(false);
      },
      error: () => this.loading.set(false),
    });
  }

  loadStats() {
    const f = this.filters();
    const params: any = {};
    if (f.campus_id) params.campus_id = f.campus_id;

    this.attestationService.getStats(params).subscribe({
      next: (res) => this.stats.set(res.data),
      error: () => {},
    });
  }

  // ═══ CHANGEMENT D'ONGLET ═══
  switchTab(tab: Tab) {
    this.activeTab.set(tab);
    this.loadAttestations(1);
  }

  applyFilters() {
    this.loadAttestations(1);
  }

  resetFilters() {
    this.filters.set({ campus_id: null, search: '' });
    this.loadAttestations(1);
  }

  goToPage(page: number) {
    if (page >= 1 && page <= this.meta().last_page) this.loadAttestations(page);
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

  // ═══ MODAL NOUVELLE DEMANDE ═══
  openModal() {
    this.showModal.set(true);
    this.modalStep.set(1);
    this.searchQuery.set('');
    this.searchResults.set([]);
    this.selectedStudent.set(null);
  }

  closeModal() {
    this.showModal.set(false);
    this.selectedStudent.set(null);
    this.searchQuery.set('');
    this.searchResults.set([]);
  }

  onSearch() {
    const q = this.searchQuery().trim();
    if (q.length < 2) { this.searchResults.set([]); return; }

    this.searching.set(true);
    this.attestationService.searchStudents(q).subscribe({
      next: (res) => {
        this.searchResults.set(res.data);
        this.searching.set(false);
      },
      error: () => this.searching.set(false),
    });
  }

  selectStudent(student: any) {
    this.selectedStudent.set(student);
    this.modalStep.set(2);
  }

  goBackToSearch() {
    this.modalStep.set(1);
    this.selectedStudent.set(null);
  }

  onSubmit() {
    const student = this.selectedStudent();
    if (!student) return;

    this.submitting.set(true);
    this.attestationService.createAttestation({
      registration_id: student.id,
    }).subscribe({
      next: () => {
        this.toastr.success('Demande d\'attestation créée avec succès');
        this.closeModal();
        this.loadAttestations(this.meta().current_page);
        this.loadStats();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de la création');
        this.submitting.set(false);
      },
    });
  }

  // ═══ RÉGLER ═══
  openSettleModal(attestation: Attestation) {
    this.attestationToSettle.set(attestation);
    this.showSettleModal.set(true);
  }

  closeSettleModal() {
    this.showSettleModal.set(false);
    this.attestationToSettle.set(null);
  }

  confirmSettle() {
    const attestation = this.attestationToSettle();
    if (!attestation) return;

    this.submitting.set(true);
    this.attestationService.settleAttestation(attestation.id).subscribe({
      next: () => {
        this.toastr.success('Attestation réglée avec succès');
        this.closeSettleModal();
        this.loadAttestations(this.meta().current_page);
        this.loadStats();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors du règlement');
        this.submitting.set(false);
      },
    });
  }

  // ═══ REMETTRE EN ATTENTE ═══
  openUnsettleModal(attestation: Attestation) {
    this.attestationToUnsettle.set(attestation);
    this.showUnsettleModal.set(true);
  }

  closeUnsettleModal() {
    this.showUnsettleModal.set(false);
    this.attestationToUnsettle.set(null);
  }

  confirmUnsettle() {
    const attestation = this.attestationToUnsettle();
    if (!attestation) return;

    this.submitting.set(true);
    this.attestationService.unsettleAttestation(attestation.id).subscribe({
      next: () => {
        this.toastr.success('Attestation remise en attente');
        this.closeUnsettleModal();
        this.loadAttestations(this.meta().current_page);
        this.loadStats();
        this.submitting.set(false);
      },
      error: (err) => {
        this.toastr.error(err.error?.message || 'Erreur lors de l\'opération');
        this.submitting.set(false);
      },
    });
  }

  // ═══ UTILITAIRES ═══
  getStudentName(a: Attestation): string {
    if (a.student) {
      return `${a.student.first_name} ${a.student.last_name}`;
    }
    return 'Étudiant inconnu';
  }

  getStudentInitials(a: Attestation): string {
    if (!a.student) return '?';
    const f = a.student.first_name?.[0] ?? '';
    const l = a.student.last_name?.[0] ?? '';
    return (f + l).toUpperCase();
  }

  formatDate(date: string | null): string {
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
  // attestations.ts

// ═══ MODAL ANNULER ═══
showCancelModal = signal(false);
attestationToCancel = signal<Attestation | null>(null);

// ...

// ═══ ANNULER ═══
openCancelModal(attestation: Attestation) {
  this.attestationToCancel.set(attestation);
  this.showCancelModal.set(true);
}

closeCancelModal() {
  this.showCancelModal.set(false);
  this.attestationToCancel.set(null);
}

confirmCancel() {
  const attestation = this.attestationToCancel();
  if (!attestation) return;

  this.submitting.set(true);
  this.attestationService.cancelAttestation(attestation.id).subscribe({
    next: () => {
      this.toastr.success('Demande annulée avec succès');
      this.closeCancelModal();
      this.loadAttestations(this.meta().current_page);
      this.loadStats();
      this.submitting.set(false);
    },
    error: (err) => {
      this.toastr.error(err.error?.message || 'Erreur lors de l\'annulation');
      this.submitting.set(false);
    },
  });
}
}