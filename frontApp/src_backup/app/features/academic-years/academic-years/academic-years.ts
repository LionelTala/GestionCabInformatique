// src/app/features/academic-years/academic-years/academic-years.ts
import { Component, signal, OnInit, inject, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { AcademicYearService, AcademicYear } from '../../../core/services/academic-year';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-academic-years',
  styleUrl: './academic-years.css',
  templateUrl: './academic-years.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AcademicYears implements OnInit {
  private service = inject(AcademicYearService);
  private toastr = inject(ToastrService);
  private auth = inject(Auth);

  years = this.service.getYears();
  currentUser = this.auth.getUser();
  loading = signal(false);
  submitting = signal(false);
  togglingId = signal<number | null>(null);

  showModal = signal(false);
  isEditing = signal(false);
  selectedYear = signal<AcademicYear | null>(null);

  yearToDelete = signal<AcademicYear | null>(null);
  showDeleteModal = signal(false);

  errors = signal<Partial<{ label: string; start_date: string; end_date: string }>>({});

  formData = signal({
    label: '',
    start_date: '',
    end_date: '',
    is_current: false,
    is_active: true,
  });

  ngOnInit(): void {
    this.loadYears();
  }

  loadYears(): void {
    this.loading.set(true);
    this.service.loadYears().subscribe({
      complete: () => this.loading.set(false),
      error: () => this.loading.set(false),
    });
  }

  // ✅ Helper : convertit une date ISO en YYYY-MM-DD pour les inputs
  private toDateInput(isoDate: string): string {
    if (!isoDate) return '';
    // Si déjà au format YYYY-MM-DD
    if (/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) return isoDate;
    // Sinon on extrait les 10 premiers caractères
    return isoDate.substring(0, 10);
  }

  validate(): boolean {
    const d = this.formData();
    const e: Partial<{ label: string; start_date: string; end_date: string }> = {};

    if (!d.label.trim()) {
      e.label = 'Le libellé est obligatoire';
    } else if (!/^\d{4}-\d{4}$/.test(d.label.trim())) {
      e.label = 'Format invalide (2024-2025)';
    }

    if (!d.start_date) {
      e.start_date = 'Date de début obligatoire';
    }

    if (!d.end_date) {
      e.end_date = 'Date de fin obligatoire';
    } else if (d.start_date && d.end_date <= d.start_date) {
      e.end_date = 'Doit être après le début';
    }

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  openCreateModal(): void {
    this.isEditing.set(false);
    this.selectedYear.set(null);
    this.errors.set({});
    this.formData.set({
      label: '', start_date: '', end_date: '',
      is_current: false, is_active: true,
    });
    this.showModal.set(true);
  }

  openEditModal(y: AcademicYear): void {
    this.isEditing.set(true);
    this.selectedYear.set(y);
    this.errors.set({});
    this.formData.set({
      // ✅ Convertir les dates ISO → YYYY-MM-DD
      label: y.label,
      start_date: this.toDateInput(y.start_date),
      end_date: this.toDateInput(y.end_date),
      is_current: !!y.is_current,
      is_active: !!y.is_active,
    });
    this.showModal.set(true);
  }

  closeModal(): void {
    this.showModal.set(false);
    this.errors.set({});
  }

  onSubmit(): void {
    if (!this.validate()) return;

    this.submitting.set(true);

    const data = this.formData();

    if (this.isEditing()) {
      // ✅ En modification, envoyer uniquement les champs modifiables
      this.service.update(this.selectedYear()!.id, {
        label: data.label,
        start_date: data.start_date,
        end_date: data.end_date,
        is_current: data.is_current,
        is_active: data.is_active,
      }).subscribe({
        next: () => {
          this.toastr.success('Année modifiée avec succès');
          this.closeModal();
          this.loadYears();
          this.submitting.set(false);
        },
        error: (err: any) => this.handleError(err),
      });
    } else {
      // ✅ En création
      this.service.create({
        label: data.label,
        start_date: data.start_date,
        end_date: data.end_date,
        is_current: data.is_current,
        is_active: data.is_active,
      }).subscribe({
        next: () => {
          this.toastr.success('Année créée avec succès');
          this.closeModal();
          this.loadYears();
          this.submitting.set(false);
        },
        error: (err: any) => this.handleError(err),
      });
    }
  }

  // ✅ Gestion centralisée des erreurs
  private handleError(err: any): void {
    if (err.status === 422 && err.error?.errors) {
      const e: Partial<{ label: string; start_date: string; end_date: string }> = {};
      for (const [key, msg] of Object.entries(err.error.errors)) {
        if (key === 'label' || key === 'start_date' || key === 'end_date') {
          e[key as keyof typeof e] = Array.isArray(msg) ? msg[0] as string : msg as string;
        }
      }
      this.errors.set(e);
      this.toastr.warning('Veuillez corriger les erreurs du formulaire');
    } else {
      this.toastr.error(err.error?.message || 'Une erreur est survenue');
    }
    this.submitting.set(false);
  }

  openDeleteModal(y: AcademicYear): void {
    this.yearToDelete.set(y);
    this.showDeleteModal.set(true);
  }

  closeDeleteModal(): void {
    this.showDeleteModal.set(false);
    this.yearToDelete.set(null);
  }

  confirmDelete(): void {
    const y = this.yearToDelete();
    if (!y) return;

    this.submitting.set(true);
    this.service.delete(y.id).subscribe({
      next: () => {
        this.toastr.success('Année supprimée avec succès');
        this.closeDeleteModal();
        this.loadYears();
        this.submitting.set(false);
      },
      error: (err: any) => {
        // ✅ Gestion des erreurs (ex : utilisateurs liés)
        if (err.status === 422) {
          this.toastr.error(err.error?.message || 'Suppression impossible : des utilisateurs sont liés à cette année.');
        } else {
          this.toastr.error(err.error?.message || 'Erreur lors de la suppression');
        }
        this.submitting.set(false);
      },
    });
  }

  toggleStatus(y: AcademicYear): void {
    this.togglingId.set(y.id);
    this.service.update(y.id, { is_active: !y.is_active }).subscribe({
      next: () => {
        this.toastr.success(y.is_active ? 'Année désactivée' : 'Année activée');
        this.loadYears();
      },
      error: (err: any) => {
        this.toastr.error(err.error?.message || 'Erreur lors du changement de statut');
        this.togglingId.set(null);
      },
      complete: () => this.togglingId.set(null),
    });
  }

  canManage(): boolean {
    return !!this.currentUser &&
      ['super_admin', 'admin_global'].includes(this.currentUser.role);
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('fr-FR', {
      day: '2-digit', month: '2-digit', year: 'numeric',
    });
  }
}